<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AuditLog;
use App\Models\FiscalYear;
use App\Services\YearEndClosingService;
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
            static::can('fiscal-year.close', ['close', 'reopen', 'togglePeriod']),
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

    /** What closing this year would post, before committing to it. */
    public function previewClosing(FiscalYear $fiscalYear, YearEndClosingService $closing)
    {
        return view('admin.accounting.fiscal-years.closing-preview', [
            'fiscalYear' => $fiscalYear,
            'preview' => $closing->preview($fiscalYear),
        ]);
    }

    /**
     * Close the year: post the closing entry, then mark it closed.
     *
     * This used to only flip the is_closed flag, so profit-and-loss balances ran
     * forever and the year's result was never carried into equity.
     */
    public function close(FiscalYear $fiscalYear, YearEndClosingService $closing)
    {
        try {
            $entry = $closing->close($fiscalYear);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log('closed', FiscalYear::class, $fiscalYear->id, null, [
            'closing_entry' => $entry->entry_number,
        ]);

        return back()->with('success', __('Fiscal year closed. Closing entry :n posted.', ['n' => $entry->entry_number]));
    }

    /** Reverse a closing and reopen the year. */
    public function reopen(FiscalYear $fiscalYear, YearEndClosingService $closing)
    {
        try {
            $closing->reopen($fiscalYear);
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
