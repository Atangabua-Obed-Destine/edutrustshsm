<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class JournalEntryController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'journal-entry';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('journal-entry.post', ['post']),
            static::can('journal-entry.unpost', ['unpost']),
        ];
    }

    public function index(Request $request)
    {
        $entries = JournalEntry::with('fiscalYear')
            ->when($request->fiscal_year_id, fn ($q, $v) => $q->where('fiscal_year_id', $v))
            ->when($request->journal_type, fn ($q, $v) => $q->where('journal_type', $v))
            ->when($request->status === 'posted', fn ($q) => $q->where('is_posted', true))
            ->when($request->status === 'draft', fn ($q) => $q->where('is_posted', false))
            ->when($request->start_date, fn ($q, $v) => $q->whereDate('entry_date', '>=', $v))
            ->when($request->end_date, fn ($q, $v) => $q->whereDate('entry_date', '<=', $v))
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $fiscalYears = FiscalYear::orderByDesc('start_date')->get();

        return view('admin.accounting.journal.index', compact('entries', 'fiscalYears'));
    }

    public function create()
    {
        $accounts = ChartOfAccount::postable()->orderBy('account_code')->get();
        return view('admin.accounting.journal.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'journal_type' => ['required', 'in:general,sales,purchase,cash,bank,adjustment,opening,closing'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Each line must be debit XOR credit; debits must equal credits.
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($validated['lines'] as $line) {
            $d = (float) ($line['debit'] ?? 0);
            $c = (float) ($line['credit'] ?? 0);
            if (($d > 0 && $c > 0) || ($d == 0 && $c == 0)) {
                return back()->withInput()->with('error', __('Each line must have either a debit or a credit, not both or neither.'));
            }
            $totalDebit += $d;
            $totalCredit += $c;
        }
        if (abs($totalDebit - $totalCredit) > 0.01) {
            return back()->withInput()->with('error', __('Entry is not balanced: debits (:d) must equal credits (:c).', ['d' => number_format($totalDebit, 2), 'c' => number_format($totalCredit, 2)]));
        }

        $fy = FiscalYear::active();
        $period = AccountingPeriod::forDate($validated['entry_date']);

        $entry = DB::transaction(function () use ($validated, $fy, $period, $totalDebit, $totalCredit) {
            $entry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $validated['entry_date'],
                'fiscal_year_id' => $fy?->id,
                'accounting_period_id' => $period?->id,
                'journal_type' => $validated['journal_type'],
                'description' => $validated['description'] ?? null,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'created_by' => auth()->id(),
            ]);

            $n = 1;
            foreach ($validated['lines'] as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'line_number' => $n++,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $entry;
        });

        AuditLog::log('created', JournalEntry::class, $entry->id, null, $entry->toArray());

        return redirect()->route('admin.journal-entries.show', $entry)->with('success', __('Journal entry created.'));
    }

    public function show(JournalEntry $journal_entry)
    {
        $journal_entry->load(['lines.account', 'fiscalYear', 'accountingPeriod', 'postedBy', 'createdBy']);
        return view('admin.accounting.journal.show', ['entry' => $journal_entry]);
    }

    public function post(JournalEntry $journal_entry)
    {
        try {
            $journal_entry->post(auth()->id());
            AuditLog::log('posted', JournalEntry::class, $journal_entry->id, null, null);
            return back()->with('success', __('Journal entry posted.'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function unpost(JournalEntry $journal_entry)
    {
        try {
            $journal_entry->unpost();
            AuditLog::log('unposted', JournalEntry::class, $journal_entry->id, null, null);
            return back()->with('success', __('Journal entry un-posted.'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(JournalEntry $journal_entry)
    {
        if ($journal_entry->is_posted) {
            return back()->with('error', __('Posted entries cannot be deleted. Un-post it first.'));
        }
        if ($journal_entry->is_system_generated) {
            return back()->with('error', __('System-generated entries cannot be deleted.'));
        }

        $old = $journal_entry->toArray();
        $journal_entry->delete();
        AuditLog::log('deleted', JournalEntry::class, $old['id'], $old, null);

        return redirect()->route('admin.journal-entries.index')->with('success', __('Journal entry deleted.'));
    }
}
