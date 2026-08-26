<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffTaxExemption;
use App\Models\TaxGroup;
use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Http\Request;

class TaxSettingController extends Controller
{
    public function index()
    {
        $settings = TaxSetting::with('taxGroup')->orderBy('bracket_order')->paginate(20);
        $groups = TaxGroup::where('status', true)->orderBy('display_order')->get();
        $dependables = TaxSetting::where('is_dependent', false)->get(['id', 'tax_title']);
        return view('admin.hr.tax.settings.index', compact('settings', 'groups', 'dependables'));
    }

    public function store(Request $request)
    {
        $data = $this->validateSetting($request);
        TaxSetting::create($data);
        return back()->with('success', __('Tax setting created.'));
    }

    public function update(Request $request, TaxSetting $tax_setting)
    {
        $data = $this->validateSetting($request);
        $tax_setting->update($data);
        return back()->with('success', __('Tax setting updated.'));
    }

    public function destroy(TaxSetting $tax_setting)
    {
        $tax_setting->delete();
        return back()->with('success', __('Tax setting deleted.'));
    }

    // ── Exemptions ──
    public function exemptions(TaxSetting $tax_setting)
    {
        $list = StaffTaxExemption::with('user')->where('tax_setting_id', $tax_setting->id)->get();
        $staff = User::staff()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'staff_id']);
        return response()->json([
            'setting' => ['id' => $tax_setting->id, 'title' => $tax_setting->tax_title],
            'exemptions' => $list->map(fn ($e) => [
                'id' => $e->id,
                'staff' => trim(($e->user->staff_id ? $e->user->staff_id . ' - ' : '') . $e->user->full_name),
                'custom' => $e->custom_percentage ?? $e->custom_fixed_amount,
                'expires_at' => optional($e->expires_at)->toDateString(),
            ]),
            'staff' => $staff->map(fn ($s) => ['id' => $s->id, 'label' => trim(($s->staff_id ? $s->staff_id . ' - ' : '') . $s->full_name)]),
        ]);
    }

    public function storeExemption(Request $request, TaxSetting $tax_setting)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'custom_percentage' => ['nullable', 'numeric', 'min:0'],
            'custom_fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:191'],
            'expires_at' => ['nullable', 'date'],
        ]);

        StaffTaxExemption::updateOrCreate(
            ['user_id' => $validated['user_id'], 'tax_setting_id' => $tax_setting->id],
            $validated
        );

        return back()->with('success', __('Exemption saved.'));
    }

    public function destroyExemption(StaffTaxExemption $exemption)
    {
        $exemption->delete();
        return back()->with('success', __('Exemption removed.'));
    }

    private function validateSetting(Request $request): array
    {
        $validated = $request->validate([
            'tax_title' => ['required', 'string', 'max:191'],
            'tax_group_id' => ['nullable', 'exists:tax_groups,id'],
            'bracket_order' => ['nullable', 'integer', 'min:0'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['required', 'numeric', 'min:0'],
            'max_no_taxable_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_type' => ['required', 'in:1,2'],
            'percentage' => ['nullable', 'numeric', 'min:0'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'employer_percentage' => ['nullable', 'numeric', 'min:0'],
            'employer_fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_by' => ['required', 'in:employee,employer,both'],
            'is_shared' => ['nullable', 'boolean'],
            'is_dependent' => ['nullable', 'boolean'],
            'depends_on_type' => ['nullable', 'in:tax_group,tax_setting'],
            'depends_on_id' => ['nullable', 'integer'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date'],
        ]);

        $validated['is_shared'] = $request->boolean('is_shared');
        $validated['is_dependent'] = $request->boolean('is_dependent');
        $validated['status'] = true;

        return $validated;
    }
}
