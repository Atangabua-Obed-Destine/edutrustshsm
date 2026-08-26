<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\SchoolSetting;

/**
 * Sets up a newly-created branch so it is immediately usable.
 *
 * Phase 1 seeds the branch's own SchoolSetting row (branding, prefixes).
 * Later phases extend provision() to clone per-branch reference data
 * (forms, subjects, grade scale, fee categories, chart of accounts, …) once
 * those tables are branch-scoped.
 */
class BranchProvisioningService
{
    public function provision(Branch $branch): void
    {
        $this->seedSettings($branch);
    }

    private function seedSettings(Branch $branch): void
    {
        if (SchoolSetting::withoutBranchScope()->where('branch_id', $branch->id)->exists()) {
            return;
        }

        // Reasonable defaults; the admin completes them in Settings.
        SchoolSetting::withoutBranchScope()->create([
            'branch_id' => $branch->id,
            'school_name' => $branch->name,
            'school_short_name' => $branch->code,
            'school_code' => $branch->code,
            'currency' => 'XAF',
            'student_id_prefix' => $branch->code,
            'receipt_prefix' => 'RCP',
            'max_terms_per_session' => 3,
            'max_sequences_per_term' => 2,
            'pass_mark' => 10.0,
            'promotion_threshold' => 10.0,
            'min_attendance_percent' => 85,
            'max_mark' => 20.0,
        ]);
    }
}
