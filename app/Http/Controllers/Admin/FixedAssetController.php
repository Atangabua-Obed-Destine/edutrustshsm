<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\DepreciationSchedule;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Services\DepreciationService;
use Illuminate\Http\Request;
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
            static::can('fixed-asset.view', ['schedule', 'categories']),
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
}
