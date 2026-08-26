<?php

namespace App\Services;

use App\Models\Guardian;

/**
 * Find-or-create a Guardian so that a single parent (identified by a shared
 * email) is represented by ONE guardian row owning ALL their children, instead
 * of a fresh duplicate row per student registration.
 *
 * Matching is by email within the active branch: any of the incoming emails
 * (guardian/father/mother) matched against any of an existing guardian's email
 * columns (guardian_email/father_email/mother_email/login_email). When a match
 * is found, the existing row is reused (and back-filled with any new contact
 * details it was missing). Otherwise a new guardian is created.
 *
 * branch_id is stamped automatically by the BelongsToBranch trait, and the
 * BranchScope keeps the lookup confined to the active branch.
 */
class GuardianResolver
{
    /**
     * @param  array  $data  Guardian attributes (same keys used by the create() calls
     *                        in StudentController / AdmissionController / BulkUpload).
     */
    public function resolve(array $data): Guardian
    {
        $emails = $this->emailsFrom($data);

        if (! empty($emails)) {
            $existing = $this->findByEmails($emails);
            if ($existing) {
                $this->backfill($existing, $data);

                return $existing;
            }
        }

        return Guardian::create($data);
    }

    /** Collect non-empty, lower-cased emails from the incoming data. */
    protected function emailsFrom(array $data): array
    {
        return collect([
            $data['guardian_email'] ?? null,
            $data['father_email'] ?? null,
            $data['mother_email'] ?? null,
            $data['login_email'] ?? null,
        ])
            ->filter()
            ->map(fn ($e) => mb_strtolower(trim($e)))
            ->unique()
            ->values()
            ->all();
    }

    /** Find a guardian (in the active branch) whose any email column matches. */
    protected function findByEmails(array $emails): ?Guardian
    {
        return Guardian::where(function ($q) use ($emails) {
            foreach (['guardian_email', 'father_email', 'mother_email', 'login_email'] as $col) {
                $q->orWhereIn($col, $emails);
            }
        })->first();
    }

    /**
     * Fill in any contact fields the matched guardian is currently missing,
     * without overwriting data it already has (the existing record is canonical).
     */
    protected function backfill(Guardian $guardian, array $data): void
    {
        $dirty = false;

        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (blank($guardian->getAttribute($key))) {
                $guardian->setAttribute($key, $value);
                $dirty = true;
            }
        }

        if ($dirty) {
            $guardian->save();
        }
    }
}
