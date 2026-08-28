<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AuditLog;
use App\Models\FiscalYear;
use Illuminate\Http\Request;
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
            static::can('fiscal-year.close', ['close', 'togglePeriod']),
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

    public function close(FiscalYear $fiscalYear)
    {
        if (! $fiscalYear->canClose()) {
            return back()->with('error', __('All periods must be closed and all entries posted before closing the year.'));
        }
        $fiscalYear->update(['is_closed' => true, 'is_active' => false]);
        AuditLog::log('closed', FiscalYear::class, $fiscalYear->id, null, null);

        return back()->with('success', __('Fiscal year closed.'));
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
