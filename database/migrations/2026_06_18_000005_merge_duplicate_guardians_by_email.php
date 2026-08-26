<?php

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time cleanup: collapse duplicate guardian rows (one parent that ended up
 * with several rows because a guardian was created per student) into a single
 * canonical row per email, per branch. All children and parent-side payment
 * records are re-pointed to the keeper, the keeper is back-filled with any
 * missing contact details, and the empty duplicates are deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pull all guardians without the branch scope (console context already
        // bypasses it, but be explicit) ordered so the lowest id wins as keeper.
        $guardians = Guardian::withoutBranchScope()->orderBy('id')->get();

        // Group by branch + normalized email. A guardian can have several email
        // columns; index it under each distinct email so any overlap merges.
        $groups = []; // key "branch|email" => [guardian ids...]

        foreach ($guardians as $g) {
            $branch = $g->branch_id ?? 0;
            $emails = collect([$g->guardian_email, $g->father_email, $g->mother_email, $g->login_email])
                ->filter()
                ->map(fn ($e) => mb_strtolower(trim($e)))
                ->unique();

            foreach ($emails as $email) {
                $groups["{$branch}|{$email}"][] = $g->id;
            }
        }

        $hasSubmissions = Schema::hasTable('parent_payment_submissions');
        $hasLevyPayments = Schema::hasTable('pta_levy_payments');

        $merged = 0;

        foreach ($groups as $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) < 2) {
                continue; // no duplicate for this email
            }

            sort($ids);
            $keeperId = array_shift($ids); // lowest id is canonical
            $keeper = Guardian::withoutBranchScope()->find($keeperId);
            if (! $keeper) {
                continue;
            }

            foreach ($ids as $dupId) {
                $dup = Guardian::withoutBranchScope()->find($dupId);
                if (! $dup) {
                    continue; // already merged via another email key
                }

                // Re-point children.
                Student::withoutGlobalScopes()
                    ->where('guardian_id', $dupId)
                    ->update(['guardian_id' => $keeperId]);

                // Re-point parent-side payment records.
                if ($hasSubmissions) {
                    DB::table('parent_payment_submissions')
                        ->where('guardian_id', $dupId)
                        ->update(['guardian_id' => $keeperId]);
                }
                if ($hasLevyPayments) {
                    DB::table('pta_levy_payments')
                        ->where('guardian_id', $dupId)
                        ->update(['guardian_id' => $keeperId]);
                }

                // Back-fill any contact fields the keeper is missing.
                $this->backfill($keeper, $dup);

                // Delete the now-empty duplicate.
                $dup->delete();
                $merged++;
            }
        }

        if ($merged > 0) {
            echo "  Merged {$merged} duplicate guardian row(s).\n";
        }
    }

    public function down(): void
    {
        // Irreversible data merge — no rollback.
    }

    protected function backfill(Guardian $keeper, Guardian $dup): void
    {
        $fields = [
            'father_name', 'father_phone', 'father_email', 'father_occupation', 'father_address',
            'mother_name', 'mother_phone', 'mother_email', 'mother_occupation', 'mother_address',
            'guardian_name', 'guardian_relationship', 'guardian_phone', 'guardian_email',
            'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone',
        ];

        $dirty = false;
        foreach ($fields as $f) {
            if (blank($keeper->getAttribute($f)) && filled($dup->getAttribute($f))) {
                $keeper->setAttribute($f, $dup->getAttribute($f));
                $dirty = true;
            }
        }

        // If the keeper never had portal access but a duplicate did, inherit it.
        if (blank($keeper->password) && filled($dup->password)) {
            $keeper->password = $dup->password;
            $keeper->login_email = $keeper->login_email ?: $dup->login_email;
            $keeper->portal_access = $keeper->portal_access || $dup->portal_access;
            $dirty = true;
        }

        if ($dirty) {
            $keeper->saveQuietly();
        }
    }
};
