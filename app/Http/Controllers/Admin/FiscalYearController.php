<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AuditLog;
use App\Models\FiscalYear;
use App\Models\User;
use App\Models\YearEndClosing;
use App\Services\YearEndChecklistService;
use App\Services\YearEndClosingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class FiscalYearController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'fiscal-year';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('fiscal-year.create', ['setActive']),
            static::can('fiscal-year.close', ['close', 'reopen', 'togglePeriod', 'confirmChecklist']),
            static::can('fiscal-year.view', ['previewClosing']),
        ];
    }

    public function index()
    {
        $fiscalYears = FiscalYear::withCount('periods')->orderByDesc('start_date')->get();
        return view('admin.accounting.fiscal-years.index', compact('fiscalYears'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        $fy = FiscalYear::create($validated + ['created_by' => auth()->id()]);
        $fy->generatePeriods();

        return back()->with('success', __('Fiscal year created with monthly periods.'));
    }

    public function setActive(FiscalYear $fiscalYear)
    {
        if ($fiscalYear->is_closed) {
            return back()->with('error', __('A closed fiscal year cannot be activated.'));
        }
        $fiscalYear->update(['is_active' => true]); // model saving hook deactivates the others

        return back()->with('success', __(':name is now the active fiscal year.', ['name' => $fiscalYear->name]));
    }

    /** What closing this year would post, and what still stands in the way. */
    public function previewClosing(FiscalYear $fiscalYear, YearEndClosingService $closing, YearEndChecklistService $checklist)
    {
        $current = YearEndClosing::currentFor($fiscalYear);
        $manual = $checklist->manual($current);

        return view('admin.accounting.fiscal-years.closing-preview', [
            'fiscalYear' => $fiscalYear,
            'preview' => $closing->preview($fiscalYear),
            'automatic' => $checklist->automatic($fiscalYear),
            'manual' => $manual,
            'outstanding' => $checklist->outstanding($fiscalYear, $current),
            'history' => YearEndClosing::with(['closedBy', 'reversedBy', 'closingEntry'])
                ->where('fiscal_year_id', $fiscalYear->id)->latest('id')->get(),
            'users' => User::whereIn('id', collect($manual)->pluck('by')->filter())->get()
                ->mapWithKeys(fn ($u) => [$u->id => $u->full_name])->all(),
        ]);
    }

    /** Tick or untick one of the confirmations a person has to make. */
    public function confirmChecklist(Request $request, FiscalYear $fiscalYear)
    {
        $validated = $request->validate([
            'item' => ['required', 'in:'.implode(',', array_keys(YearEndChecklistService::MANUAL))],
            'confirmed' => ['required', 'boolean'],
        ]);

        if ($fiscalYear->is_closed) {
            return back()->with('error', __('This fiscal year is already closed.'));
        }

        $closing = YearEndClosing::currentFor($fiscalYear) ?? YearEndClosing::create([
            'branch_id' => $fiscalYear->branch_id,
            'fiscal_year_id' => $fiscalYear->id,
            'status' => YearEndClosing::STATUS_IN_PROGRESS,
            'started_by' => auth()->id(),
        ]);

        $confirmations = $closing->confirmations ?? [];

        if ($request->boolean('confirmed')) {
            $confirmations[$validated['item']] = ['by' => auth()->id(), 'at' => now()->toDateTimeString()];
        } else {
            unset($confirmations[$validated['item']]);
        }

        $closing->update(['confirmations' => $confirmations]);

        return back();
    }

    /**
     * Close the year: post the closing entry, then mark it closed.
     *
     * This used to only flip the is_closed flag, so profit-and-loss balances ran
     * forever and the year's result was never carried into equity.
     */
    public function close(FiscalYear $fiscalYear, YearEndClosingService $closing, YearEndChecklistService $checklist)
    {
        $record = YearEndClosing::currentFor($fiscalYear);

        // Closing locks the year's result, so it waits until every check has
        // passed and every confirmation has been made.
        if ($outstanding = $checklist->outstanding($fiscalYear, $record)) {
            return back()->with('error', __('The year cannot be closed yet.').' '.implode(' ', $outstanding));
        }

        $preview = $closing->preview($fiscalYear);

        try {
            $entry = DB::transaction(function () use ($closing, $fiscalYear, $record, $preview) {
                $entry = $closing->close($fiscalYear);

                $record->update([
                    'status' => YearEndClosing::STATUS_CLOSED,
                    'total_revenue' => $preview['revenue'],
                    'total_expenses' => $preview['expenses'],
                    'net_result' => $preview['net'],
                    'closing_entry_id' => $entry->id,
                    'closed_by' => auth()->id(),
                    'closed_at' => now(),
                ]);

                return $entry;
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log('closed', FiscalYear::class, $fiscalYear->id, null, [
            'closing_entry' => $entry->entry_number,
        ]);

        return back()->with('success', __('Fiscal year closed. Closing entry :n posted.', ['n' => $entry->entry_number]));
    }

    /** Reverse a closing and reopen the year. */
    public function reopen(Request $request, FiscalYear $fiscalYear, YearEndClosingService $closing)
    {
        $validated = $request->validate([
            // Required: the next person to close the year needs to know what
            // was wrong with the last close.
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($closing, $fiscalYear, $validated) {
                $closing->reopen($fiscalYear);

                YearEndClosing::where('fiscal_year_id', $fiscalYear->id)
                    ->where('status', YearEndClosing::STATUS_CLOSED)
                    ->update([
                        'status' => YearEndClosing::STATUS_REVERSED,
                        'reversed_by' => auth()->id(),
                        'reversed_at' => now(),
                        'reversal_reason' => $validated['reason'],
                    ]);
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log('reopened', FiscalYear::class, $fiscalYear->id, null, null);

        return back()->with('success', __('Fiscal year reopened and its closing entry reversed.'));
    }

    public function destroy(FiscalYear $fiscalYear)
    {
        if ($fiscalYear->journalEntries()->exists()) {
            return back()->with('error', __('Cannot delete a fiscal year that has journal entries.'));
        }
        $fiscalYear->periods()->delete();
        $fiscalYear->delete();

        return back()->with('success', __('Fiscal year deleted.'));
    }

    /** Toggle a single accounting period open/closed. */
    public function togglePeriod(\App\Models\AccountingPeriod $period)
    {
        if ($period->fiscalYear->is_closed) {
            return back()->with('error', __('Fiscal year is closed.'));
        }
        if (! $period->is_closed && $period->journalEntries()->where('is_posted', false)->exists()) {
            return back()->with('error', __('All entries in the period must be posted before closing it.'));
        }
        $period->update(['is_closed' => ! $period->is_closed]);

        return back()->with('success', __('Period status updated.'));
    }
}
