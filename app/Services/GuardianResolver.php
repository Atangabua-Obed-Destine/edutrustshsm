<?php

namespace App\Services;

use App\Models\Guardian;

/**
 * Find-or-create a Guardian so that a single parent (identified by a shared
 * email) is represented by ONE guardian row owning ALL their children, instead
 * of a fresh duplicate row per student registration.
 *
 * Matching happens on email first and then on phone, within the active
 * branch. Email alone was not enough: the intake form does not require one,
 * so in practice most parents have none, and every registration created a
 * fresh row. Six real parents ended up split across fourteen records with
 * one child each, which is exactly what this class exists to prevent.
 *
 * Phones are compared on their last nine digits so that 677 123 456,
 * +237677123456 and 00237-677-123-456 are recognised as one number. Every
 * phone column is considered, including the emergency contact, which is the
 * only one intake insists on.
 *
 * When a match is found the existing row is reused and back-filled with any
 * contact details it was missing. Otherwise a new guardian is created.
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
        $existing = $this->findByEmails($this->emailsFrom($data))
            ?? $this->findByPhones($this->phonesFrom($data));

        if ($existing) {
            $this->backfill($existing, $data);

            return $existing;
        }

        return Guardian::create($data);
    }

    /**
     * Collect every phone in the incoming data, reduced to comparable digits.
     *
     * @return array<int, string>
     */
    protected function phonesFrom(array $data): array
    {
        return collect([
            $data['guardian_phone'] ?? null,
            $data['father_phone'] ?? null,
            $data['mother_phone'] ?? null,
            $data['emergency_contact_phone'] ?? null,
        ])
            ->map(fn ($p) => static::normalisePhone($p))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The comparable form of a phone number: its last nine digits.
     *
     * Cameroonian subscriber numbers are nine digits, so this treats a number
     * written with a country code, with spaces, or bare, as the same number.
     * Anything shorter is returned as its digits and only matches itself.
     */
    public static function normalisePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        return strlen($digits) > 9 ? substr($digits, -9) : $digits;
    }

    /**
     * Find a guardian in the active branch reachable on one of these numbers.
     *
     * Compared in PHP rather than SQL because the stored numbers are written
     * however the clerk typed them; normalising both sides in the database
     * would mean a different expression for every engine.
     *
     * @param  array<int, string>  $phones
     */
    protected function findByPhones(array $phones): ?Guardian
    {
        if (empty($phones)) {
            return null;
        }

        $columns = ['guardian_phone', 'father_phone', 'mother_phone', 'emergency_contact_phone'];

        return Guardian::query()
            ->where(function ($q) use ($columns) {
                foreach ($columns as $column) {
                    $q->orWhere(fn ($sub) => $sub->whereNotNull($column)->where($column, '!=', ''));
                }
            })
            ->get()
            ->first(function (Guardian $guardian) use ($phones, $columns) {
                foreach ($columns as $column) {
                    $stored = static::normalisePhone($guardian->getAttribute($column));

                    if ($stored !== null && in_array($stored, $phones, true)) {
                        return true;
                    }
                }

                return false;
            });
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
        if (empty($emails)) {
            return null;
        }

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
