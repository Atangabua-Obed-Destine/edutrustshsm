<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\DefaultAccountMapping;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Payment;
use App\Models\TransactionMapping;
use App\Services\TransactionAutoMapService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AccountMappingController extends Controller
{
    public function __construct(private TransactionAutoMapService $mapper)
    {
    }

    public function index()
    {
        $accounts = ChartOfAccount::postable()->orderBy('account_code')->get();
        $mappings = DefaultAccountMapping::get()->keyBy(fn ($m) => $m->mapping_type . ':' . ($m->category_id ?? 'all'));

        $groups = [
            'income' => IncomeCategory::where('status', true)->orderBy('title')->get(['id', 'title']),
            'expense' => ExpenseCategory::where('status', true)->orderBy('title')->get(['id', 'title']),
        ];

        return view('admin.accounting.mappings.index', compact('accounts', 'mappings', 'groups'));
    }

    /** Save one mapping rule (type + optional category → debit/credit accounts). */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'mapping_type' => ['required', 'in:income,expense,fee_payment'],
            'category_id' => ['nullable', 'integer'],
            'debit_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'credit_account_id' => ['required', 'exists:chart_of_accounts,id', 'different:debit_account_id'],
        ]);

        DefaultAccountMapping::updateOrCreate(
            ['mapping_type' => $validated['mapping_type'], 'category_id' => $validated['category_id'] ?: null],
            [
                'debit_account_id' => $validated['debit_account_id'],
                'credit_account_id' => $validated['credit_account_id'],
                'status' => 'active',
            ]
        );

        return back()->with('success', __('Mapping saved.'));
    }

    /** Operational facts that have no posted journal entry yet (no/inactive mapping). */
    public function unmapped()
    {
        $mappedIds = fn (string $type) => TransactionMapping::where('transaction_type', $type)
            ->where('status', 'active')->pluck('transaction_id');

        $rows = collect();
        foreach ([
            ['income', Income::class, 'title'],
            ['expense', Expense::class, 'title'],
            ['fee_payment', Payment::class, 'receipt_number'],
        ] as [$type, $model, $label]) {
            $model::whereNotIn('id', $mappedIds($type))->latest('id')->limit(100)->get()
                ->each(function ($r) use (&$rows, $type, $label) {
                    $rows->push((object) [
                        'type' => $type,
                        'id' => $r->id,
                        'label' => $r->{$label},
                        'amount' => $r->amount,
                        'date' => $r->date ?? $r->payment_date,
                    ]);
                });
        }

        $transactions = $rows->sortByDesc('date')->values();

        return view('admin.accounting.mappings.unmapped', compact('transactions'));
    }

    /**
     * Post every currently-unmapped fact through the auto-mapper.
     *
     * Auto-posting fails silently when no DefaultAccountMapping exists — the
     * service logs a warning and returns false, and the observers ignore it. So
     * facts recorded before the mappings were configured never reach the ledger
     * and nothing retries them. This is that retry.
     */
    public function postUnmapped()
    {
        $posted = 0;
        $skipped = 0;

        foreach ($this->unmappedFacts() as $fact) {
            $ok = $this->mapper->autoMap($fact->type, $fact->id, $fact->category_id, [
                'amount' => $fact->amount,
                'date' => $fact->date ? Carbon::parse($fact->date)->toDateString() : now()->toDateString(),
                'description' => $fact->label,
            ]);

            $ok ? $posted++ : $skipped++;
        }

        if ($posted === 0 && $skipped === 0) {
            return back()->with('success', __('Nothing to post — every transaction is already in the ledger.'));
        }

        $message = trans_choice(':count transaction posted to the ledger.|:count transactions posted to the ledger.', $posted, ['count' => $posted]);
        if ($skipped > 0) {
            $message .= ' ' . trans_choice(':count still has no account mapping.|:count still have no account mapping.', $skipped, ['count' => $skipped]);
        }

        return back()->with($skipped > 0 ? 'error' : 'success', $message);
    }

    /**
     * Operational facts with no active TransactionMapping, with the category id
     * the auto-mapper needs to pick a rule.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function unmappedFacts()
    {
        $mappedIds = fn (string $type) => TransactionMapping::where('transaction_type', $type)
            ->where('status', 'active')->pluck('transaction_id');

        $rows = collect();

        foreach ([
            ['income', Income::class, 'title', 'category_id', 'date'],
            ['expense', Expense::class, 'title', 'category_id', 'date'],
            ['fee_payment', Payment::class, 'receipt_number', null, 'payment_date'],
        ] as [$type, $model, $label, $categoryKey, $dateKey]) {
            $model::whereNotIn('id', $mappedIds($type))->orderBy('id')->get()
                ->each(function ($r) use (&$rows, $type, $label, $categoryKey, $dateKey) {
                    $rows->push((object) [
                        'type' => $type,
                        'id' => $r->id,
                        'label' => $r->{$label},
                        'amount' => $r->amount,
                        'date' => $r->{$dateKey},
                        'category_id' => $categoryKey ? $r->{$categoryKey} : null,
                    ]);
                });
        }

        return $rows;
    }
}
