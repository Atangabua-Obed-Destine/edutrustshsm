<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\DepreciationSchedule;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Services\DepreciationService;
use App\Models\FiscalYear;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use RuntimeException;

class FixedAssetController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'fixed-asset';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('fixed-asset.view', ['schedule', 'categories', 'register', 'depreciationReport']),
            static::can('fixed-asset.depreciate', ['generate', 'postPeriod', 'postDue']),
            static::can('fixed-asset.dispose', ['dispose']),
            static::can('fixed-asset.create', ['storeCategory']),
        ];
    }

    public function index(Request $request)
    {
        $assets = FixedAsset::with('category:id,name')
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('category_id'), fn ($q, $v) => $q->where('fixed_asset_category_id', $v))
            ->orderByDesc('acquisition_date')
            ->paginate(25)
            ->withQueryString();

        $assets->getCollection()->transform(function (FixedAsset $asset) {
            $asset->accumulated = $asset->accumulatedDepreciation();
            $asset->net_book_value = $asset->bookValue();

            return $asset;
        });

        return view('admin.accounting.assets.index', [
            'assets' => $assets,
            'categories' => FixedAssetCategory::active()->orderBy('name')->get(),
            'totalCost' => (float) FixedAsset::active()->sum('cost'),
            'dueCount' => DepreciationSchedule::due()
                ->whereHas('asset', fn ($q) => $q->where('status', 'active'))->count(),
        ]);
    }

    public function store(Request $request, DepreciationService $depreciation)
    {
        $validated = $request->validate([
            'fixed_asset_category_id' => ['required', 'exists:fixed_asset_categories,id'],
            'code' => ['required', 'string', 'max:40', 'unique:fixed_assets,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'min:0.01'],
            // Salvage must stay below cost or there is nothing to depreciate.
            'salvage_value' => ['nullable', 'numeric', 'min:0', 'lt:cost'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'method' => ['required', 'in:straight_line,declining'],
            'declining_rate' => ['nullable', 'numeric', 'min:0.01', 'max:100', 'required_if:method,declining'],
            'location' => ['nullable', 'string', 'max:150'],
        ]);

        $asset = FixedAsset::create($validated + [
            'salvage_value' => $validated['salvage_value'] ?? 0,
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);

        try {
            $depreciation->generateSchedule($asset);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Asset registered and its depreciation schedule generated.'));
    }

    public function schedule(FixedAsset $fixedAsset)
    {
        return view('admin.accounting.assets.schedule', [
            'asset' => $fixedAsset->load('category', 'schedules.journalEntry'),
        ]);
    }

    public function generate(FixedAsset $fixedAsset, DepreciationService $depreciation)
    {
        try {
            $count = $depreciation->generateSchedule($fixedAsset);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', trans_choice(
            ':count period generated.|:count periods generated.',
            $count,
            ['count' => $count]
        ));
    }

    public function postPeriod(DepreciationSchedule $schedule, DepreciationService $depreciation)
    {
        try {
            $entry = $depreciation->post($schedule);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Depreciation posted. Entry :n.', ['n' => $entry->entry_number]));
    }

    /** Post every period that has come due, across all active assets. */
    public function postDue(DepreciationService $depreciation)
    {
        $result = $depreciation->postDue();

        if ($result['errors']) {
            return back()->with('error', implode(' | ', $result['errors']));
        }

        return back()->with('success', trans_choice(
            ':count period posted.|:count periods posted.',
            $result['posted'],
            ['count' => $result['posted']]
        ));
    }

    public function dispose(Request $request, FixedAsset $fixedAsset, DepreciationService $depreciation)
    {
        $validated = $request->validate([
            'disposal_date' => ['required', 'date'],
            'disposal_amount' => ['required', 'numeric', 'min:0'],
            'disposal_note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = $depreciation->dispose(
                $fixedAsset,
                (float) $validated['disposal_amount'],
                $validated['disposal_date'],
                $validated['disposal_note'] ?? null
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Asset disposed. Entry :n.', ['n' => $entry->entry_number]));
    }

    public function categories()
    {
        return view('admin.accounting.assets.categories', [
            'categories' => FixedAssetCategory::with(['assetAccount', 'depreciationAccount', 'accumulatedAccount'])
                ->withCount('assets')->orderBy('name')->get(),
            'accounts' => ChartOfAccount::postable()->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name', 'class_number']),
        ]);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'method' => ['required', 'in:straight_line,declining'],
            'declining_rate' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'asset_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'depreciation_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'accumulated_account_id' => ['required', 'exists:chart_of_accounts,id'],
        ]);

        FixedAssetCategory::create($validated + ['is_active' => true]);

        return back()->with('success', __('Asset category created.'));
    }

    public function edit(FixedAsset $fixedAsset)
    {
        return view('admin.accounting.assets.edit', [
            'asset' => $fixedAsset,
            'categories' => FixedAssetCategory::orderBy('name')->get(),
            'posted' => $fixedAsset->schedules()->where('is_posted', true)->exists(),
        ]);
    }

    /**
     * Change an asset.
     *
     * The reference system allows only descriptive fields to change. Here the
     * financial terms — cost, life, method, category — can still be corrected
     * while nothing has been posted, and the schedule is rebuilt from them. Once
     * a period is in the ledger they are fixed: changing them then would leave
     * the posted depreciation computed on terms the asset no longer has.
     */
    public function update(Request $request, FixedAsset $fixedAsset, DepreciationService $depreciation)
    {
        if ($fixedAsset->isDisposed()) {
            return back()->with('error', __('A disposed asset is a closed record and cannot be changed.'));
        }

        $validated = $request->validate([
            'fixed_asset_category_id' => ['required', 'exists:fixed_asset_categories,id'],
            'code' => ['required', 'string', 'max:40', 'unique:fixed_assets,code,'.$fixedAsset->id],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'min:0.01'],
            'salvage_value' => ['nullable', 'numeric', 'min:0', 'lt:cost'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'method' => ['required', 'in:straight_line,declining'],
            'declining_rate' => ['nullable', 'numeric', 'min:0.01', 'max:100', 'required_if:method,declining'],
            'location' => ['nullable', 'string', 'max:150'],
        ]);

        $validated['salvage_value'] = $validated['salvage_value'] ?? 0;
        $changed = $this->changedTerms($fixedAsset, $validated);
        $posted = $fixedAsset->schedules()->where('is_posted', true)->exists();

        if ($posted && $changed !== []) {
            return back()->withInput()->with('error', __('Depreciation has already been posted for this asset, so these can no longer change: :fields. Dispose of it and register it again if its terms were wrong.', [
                'fields' => implode(', ', array_map(fn ($f) => __(ucfirst(str_replace('_', ' ', $f))), $changed)),
            ]));
        }

        try {
            DB::transaction(function () use ($fixedAsset, $validated, $changed, $depreciation) {
                $fixedAsset->update($validated);

                if ($changed !== []) {
                    $depreciation->generateSchedule($fixedAsset->fresh());
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.fixed-assets.schedule', $fixedAsset)->with('success', $changed === []
            ? __('Asset updated.')
            : __('Asset updated and its depreciation schedule rebuilt.'));
    }

    /**
     * Delete an asset that never reached the ledger.
     *
     * Anything posted — a depreciation period or a disposal — is a record the
     * accounts depend on, so such an asset is disposed of rather than deleted.
     */
    public function destroy(FixedAsset $fixedAsset)
    {
        if ($fixedAsset->isDisposed() || $fixedAsset->schedules()->where('is_posted', true)->exists()) {
            return back()->with('error', __('This asset has entries in the ledger, so it cannot be deleted. Dispose of it instead.'));
        }

        $fixedAsset->delete();

        return redirect()->route('admin.fixed-assets.index')->with('success', __('Asset deleted.'));
    }

    /** Every asset with its cost, depreciation to date and book value. */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'as_of' => ['nullable', 'date'],
            'category_id' => ['nullable', 'exists:fixed_asset_categories,id'],
            'status' => ['nullable', 'in:active,disposed,written_off'],
        ]);

        $asOf = $validated['as_of'] ?? Carbon::today()->toDateString();

        $rows = FixedAsset::with('category:id,name')
            ->whereDate('acquisition_date', '<=', $asOf)
            ->when($validated['category_id'] ?? null, fn ($q, $v) => $q->where('fixed_asset_category_id', $v))
            ->when($validated['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->withSum(['schedules as posted_to_date' => fn ($q) => $q->where('is_posted', true)->whereDate('period_date', '<=', $asOf)], 'amount')
            ->orderBy('code')
            ->get()
            ->map(function (FixedAsset $asset) {
                $accumulated = round((float) $asset->posted_to_date, 2);

                return (object) [
                    'asset' => $asset,
                    'cost' => (float) $asset->cost,
                    'accumulated' => $accumulated,
                    'book_value' => round((float) $asset->cost - $accumulated, 2),
                ];
            });

        $totals = [
            'cost' => $rows->sum('cost'),
            'accumulated' => $rows->sum('accumulated'),
            'book_value' => $rows->sum('book_value'),
        ];

        if ($request->input('export') === 'csv') {
            return $this->csv('asset-register-'.$asOf.'.csv',
                [__('Code'), __('Asset'), __('Category'), __('Acquired'), __('Status'), __('Cost'), __('Depreciated'), __('Book Value')],
                $rows->map(fn ($r) => [$r->asset->code, $r->asset->name, $r->asset->category?->name, $r->asset->acquisition_date?->format('Y-m-d'),
                    $r->asset->status, $r->cost, $r->accumulated, $r->book_value])->all(),
                ['', __('Total'), '', '', '', $totals['cost'], $totals['accumulated'], $totals['book_value']]);
        }

        return view('admin.accounting.assets.register', [
            'rows' => $rows,
            'totals' => $totals,
            'asOf' => $asOf,
            'categories' => FixedAssetCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Depreciation charged over a period, posted and pending, by category. */
    public function depreciationReport(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'category_id' => ['nullable', 'exists:fixed_asset_categories,id'],
        ]);

        $fiscalYear = FiscalYear::active();
        $from = $validated['from'] ?? ($fiscalYear?->start_date?->toDateString() ?? Carbon::today()->startOfYear()->toDateString());
        $to = $validated['to'] ?? ($fiscalYear?->end_date?->toDateString() ?? Carbon::today()->endOfYear()->toDateString());

        $lines = DepreciationSchedule::with(['asset.category:id,name', 'journalEntry:id,entry_number'])
            ->whereDate('period_date', '>=', $from)
            ->whereDate('period_date', '<=', $to)
            ->whereHas('asset', fn ($q) => $q->when($validated['category_id'] ?? null, fn ($a, $v) => $a->where('fixed_asset_category_id', $v)))
            ->orderBy('period_date')
            ->get();

        $byCategory = $lines->groupBy(fn ($l) => $l->asset?->category?->name ?? __('Uncategorised'))
            ->map(fn ($group, $name) => (object) [
                'name' => $name,
                'assets' => $group->pluck('fixed_asset_id')->unique()->count(),
                'posted' => round((float) $group->where('is_posted', true)->sum('amount'), 2),
                'pending' => round((float) $group->where('is_posted', false)->sum('amount'), 2),
            ])
            ->sortKeys()
            ->values();

        $totals = [
            'posted' => round((float) $lines->where('is_posted', true)->sum('amount'), 2),
            'pending' => round((float) $lines->where('is_posted', false)->sum('amount'), 2),
            // Pending periods whose date has already passed: charges the ledger
            // is missing, not charges still to come.
            'overdue' => $lines->where('is_posted', false)->filter(fn ($l) => $l->period_date->lte(Carbon::today()))->count(),
        ];

        if ($request->input('export') === 'csv') {
            return $this->csv('depreciation-'.$from.'-'.$to.'.csv',
                [__('Period'), __('Code'), __('Asset'), __('Category'), __('Amount'), __('Accumulated'), __('Book Value'), __('Status'), __('Entry')],
                $lines->map(fn ($l) => [$l->period_date->format('Y-m-d'), $l->asset?->code, $l->asset?->name, $l->asset?->category?->name,
                    $l->amount, $l->accumulated, $l->book_value, $l->is_posted ? __('Posted') : __('Pending'), $l->journalEntry?->entry_number])->all(),
                ['', '', __('Total posted'), '', $totals['posted'], '', '', '', '']);
        }

        return view('admin.accounting.assets.depreciation-report', [
            'lines' => $lines,
            'byCategory' => $byCategory,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'categories' => FixedAssetCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * The financial terms that differ between an asset and submitted values.
     *
     * @param  array<string, mixed>  $input
     * @return array<int, string>
     */
    private function changedTerms(FixedAsset $asset, array $input): array
    {
        $changed = [];

        foreach (['fixed_asset_category_id', 'acquisition_date', 'cost', 'salvage_value', 'useful_life_years', 'method', 'declining_rate'] as $field) {
            $before = $asset->{$field};
            $after = $input[$field] ?? null;

            $same = match ($field) {
                'acquisition_date' => $before?->toDateString() === Carbon::parse($after)->toDateString(),
                'cost', 'salvage_value', 'declining_rate' => abs((float) $before - (float) $after) < 0.005,
                'method' => (string) $before === (string) $after,
                default => (int) $before === (int) $after,
            };

            if (! $same) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, mixed>  $footer
     */
    private function csv(string $filename, array $header, array $rows, array $footer)
    {
        return response()->streamDownload(function () use ($header, $rows, $footer) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fputcsv($out, $footer);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
