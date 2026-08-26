<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeScale;
use App\Models\SchoolSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function index()
    {
        if (\App\Support\BranchContext::isAllBranches()) {
            return redirect()->route('admin.dashboard')
                ->with('error', __('Settings are per-branch. Select a specific branch first.'));
        }

        $settings = SchoolSetting::current() ?? new SchoolSetting();
        $gradeScales = GradeScale::orderBy('display_order')->get();

        return view('admin.settings.index', compact('settings', 'gradeScales'));
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
