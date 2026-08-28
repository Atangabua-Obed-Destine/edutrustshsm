<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\FeeCategory;
use App\Models\FeeFine;
use App\Models\StudentFee;
use App\Services\FeeFineService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class FeeFineController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'fee-fine';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('fee-fine.edit', ['accrue']),
        ];
    }

    public function index()
    {
        return view('admin.fees.fines.index', [
            'fines' => FeeFine::with('feeCategories:id,name')->orderBy('start_day')->get(),
            'categories' => FeeCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'accrued' => (float) StudentFee::where('fine_amount', '>', 0)->sum('fine_amount'),
            'feesCharged' => StudentFee::where('fine_amount', '>', 0)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $fine = FeeFine::create(collect($validated)->except('fee_categories')->all());
            $fine->feeCategories()->sync($validated['fee_categories'] ?? []);
        });

        return back()->with('success', __('Late fee band created.'));
    }

    public function update(Request $request, FeeFine $feeFine)
    {
        $validated = $this->validated($request, $feeFine);

        DB::transaction(function () use ($validated, $feeFine) {
            $feeFine->update(collect($validated)->except('fee_categories')->all());
            $feeFine->feeCategories()->sync($validated['fee_categories'] ?? []);
        });

        return back()->with('success', __('Late fee band updated.'));
    }

    public function destroy(FeeFine $feeFine)
    {
        $feeFine->delete();

        return back()->with('success', __('Late fee band deleted. Re-run accrual to clear its charges.'));
    }

    /**
     * Run accrual now rather than waiting for the nightly schedule.
     *
     * Safe to press repeatedly: accrual recomputes each fee's penalty from the
     * bands rather than adding to it.
     */
    public function accrue(FeeFineService $fines)
    {
        $result = $fines->accrueAll();

        return back()->with('success', __(':examined overdue fees checked, :charged carrying a penalty.', [
            'examined' => $result['examined'],
            'charged' => $result['charged'],
        ]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?FeeFine $existing = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'start_day' => ['required', 'integer', 'min:1', 'max:3650'],
            // Open-ended by design: the final band usually has no upper bound.
            'end_day' => ['nullable', 'integer', 'min:1', 'max:3650', 'gte:start_day'],
            'type' => ['required', 'in:fixed,percentage'],
            'amount' => ['required', 'numeric', 'min:0', $request->input('type') === 'percentage' ? 'max:100' : 'max:99999999'],
            'is_active' => ['nullable', 'boolean'],
            'fee_categories' => ['nullable', 'array'],
            'fee_categories.*' => ['integer', 'exists:fee_categories,id'],
        ]);
    }
}
