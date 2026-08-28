<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\GradeScale;
use App\Models\SchoolSetting;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'school-settings';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('school-settings.view', ['index']),
            static::can('school-settings.edit', ['update', 'updateGradeScale', 'setLevelMode', 'updateGroup']),
        ];
    }

    public function index()
    {
        if (\App\Support\BranchContext::isAllBranches()) {
            return redirect()->route('admin.dashboard')
                ->with('error', __('Settings are per-branch. Select a specific branch first.'));
        }

        $settings = SchoolSetting::current() ?? new SchoolSetting();
        $gradeScales = GradeScale::orderBy('display_order')->get();
        $kv = Setting::allForBranch();

        return view('admin.settings.index', compact('settings', 'gradeScales', 'kv'));
    }

    /**
     * Save one tab of key/value settings (receipt, report card, mail, SMS...).
     *
     * Kept as a single endpoint over a typed key/value store rather than the
     * reference system's 22 separate *SettingController classes and 25
     * single-row tables, none of which were tenant-aware.
     */
    public function updateGroup(Request $request, string $group)
    {
        $schema = $this->groupSchema();

        abort_unless(isset($schema[$group]), 404);

        $validated = $request->validate($schema[$group]['rules']);

        foreach ($schema[$group]['fields'] as $key => $type) {
            $value = $validated[str_replace('.', '_', $key)] ?? null;

            if ($type === 'bool') {
                $value = (bool) $value;
            }

            Setting::put($key, $value, $group, $type, $schema[$group]['encrypted'][$key] ?? false);
        }

        return back()->with('success', __('Settings saved.'));
    }

    /**
     * Declarative definition of every key/value settings tab: the keys it owns,
     * their types, which are secrets, and the validation for the form.
     *
     * @return array<string, array{fields: array<string, string>, rules: array<string, mixed>, encrypted?: array<string, bool>}>
     */
    private function groupSchema(): array
    {
        return [
            'receipt' => [
                'fields' => [
                    'receipt.header' => 'string',
                    'receipt.footer' => 'string',
                    'receipt.show_logo' => 'bool',
                    'receipt.signature_label' => 'string',
                ],
                'rules' => [
                    'receipt_header' => ['nullable', 'string', 'max:255'],
                    'receipt_footer' => ['nullable', 'string', 'max:500'],
                    'receipt_show_logo' => ['nullable', 'boolean'],
                    'receipt_signature_label' => ['nullable', 'string', 'max:100'],
                ],
            ],
            'report-card' => [
                'fields' => [
                    'report_card.principal_name' => 'string',
                    'report_card.footer_note' => 'string',
                    'report_card.show_class_average' => 'bool',
                    'report_card.show_rank' => 'bool',
                ],
                'rules' => [
                    'report_card_principal_name' => ['nullable', 'string', 'max:150'],
                    'report_card_footer_note' => ['nullable', 'string', 'max:500'],
                    'report_card_show_class_average' => ['nullable', 'boolean'],
                    'report_card_show_rank' => ['nullable', 'boolean'],
                ],
            ],
            'mail' => [
                'fields' => [
                    'mail.host' => 'string',
                    'mail.port' => 'int',
                    'mail.username' => 'string',
                    'mail.password' => 'string',
                    'mail.encryption' => 'string',
                    'mail.from_address' => 'string',
                    'mail.from_name' => 'string',
                ],
                'encrypted' => ['mail.password' => true],
                'rules' => [
                    'mail_host' => ['nullable', 'string', 'max:150'],
                    'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                    'mail_username' => ['nullable', 'string', 'max:150'],
                    'mail_password' => ['nullable', 'string', 'max:255'],
                    'mail_encryption' => ['nullable', 'in:tls,ssl,none'],
                    'mail_from_address' => ['nullable', 'email', 'max:150'],
                    'mail_from_name' => ['nullable', 'string', 'max:150'],
                ],
            ],
        ];
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'school_name' => ['required', 'string', 'max:200'],
            'school_short_name' => ['nullable', 'string', 'max:50'],
            'school_code' => ['required', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'po_box' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'url', 'max:200'],
            'motto' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:10'],
            'student_id_prefix' => ['required', 'string', 'max:20'],
            'receipt_prefix' => ['required', 'string', 'max:20'],
            'max_terms_per_session' => ['required', 'integer', 'min:1', 'max:4'],
            'max_sequences_per_term' => ['required', 'integer', 'min:1', 'max:3'],
            'pass_mark' => ['required', 'numeric', 'min:0', 'max:20'],
            'promotion_threshold' => ['required', 'numeric', 'min:0', 'max:20'],
            'min_attendance_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'max_mark' => ['required', 'numeric', 'min:1', 'max:100'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $settings = SchoolSetting::current() ?? new SchoolSetting();

        if ($request->hasFile('logo')) {
            if ($settings->logo) {
                Storage::disk('public')->delete($settings->logo);
            }
            $validated['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $settings->fill($validated);
        $settings->save();

        return redirect()->route('admin.settings.index')
            ->with('success', 'School settings updated successfully.');
    }

    public function updateGradeScale(Request $request)
    {
        $validated = $request->validate([
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.grade' => ['required', 'string', 'max:5'],
            'grades.*.min_mark' => ['required', 'numeric', 'min:0'],
            'grades.*.max_mark' => ['required', 'numeric', 'min:0'],
            'grades.*.description' => ['required', 'string', 'max:100'],
        ]);

        // Replace only THIS branch's grade scale (never truncate — that would
        // wipe every branch's scale; truncate also ignores the global scope).
        GradeScale::query()->delete();

        foreach ($validated['grades'] as $i => $grade) {
            GradeScale::create([
                'grade' => $grade['grade'],
                'min_mark' => $grade['min_mark'],
                'max_mark' => $grade['max_mark'],
                'description' => $grade['description'],
                'display_order' => $i + 1,
            ]);
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'Grade scale updated successfully.');
    }

    /**
     * Set/change the School Level Mode (Nursery/Primary, Secondary, or Both).
     * Used both by the forced-setup modal and the Settings page. Narrowing the
     * mode never deletes data — it warns when classes exist for a level that
     * will be hidden.
     */
    public function setLevelMode(Request $request)
    {
        $validated = $request->validate([
            'school_level_mode' => ['required', 'in:nursery_primary,secondary,both'],
        ]);

        $settings = SchoolSetting::current() ?? new SchoolSetting();
        $settings->school_level_mode = $validated['school_level_mode'];
        $settings->save();

        // Warn (but allow) if narrowing hides existing classes for an excluded level.
        $excluded = array_diff(['nursery_primary', 'secondary'], $settings->activeSchoolLevels());
        $hidden = $excluded
            ? \App\Models\Form::whereIn('school_level', $excluded)->count()
            : 0;

        $label = [
            'nursery_primary' => __('Nursery / Primary only'),
            'secondary' => __('Secondary / High School only'),
            'both' => __('Nursery/Primary and Secondary/High'),
        ][$settings->school_level_mode];

        $message = __('School level mode set to ":mode".', ['mode' => $label]);
        if ($hidden > 0) {
            return redirect()->route('admin.settings.index')
                ->with('warning', $message . ' ' . __(':count existing class(es) for the disabled level are now hidden (not deleted). Re-enable that level to see them again.', ['count' => $hidden]));
        }

        return redirect()->route('admin.settings.index')->with('success', $message);
    }
}
