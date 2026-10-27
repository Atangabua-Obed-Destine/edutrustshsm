<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\ParentPaymentSubmission;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finding and joining parents the system recorded more than once.
 *
 * Guardians were matched on email alone, and the intake form never required
 * one, so a parent enrolling a second child was created afresh. The damage is
 * already done in existing data; this finds it and offers to undo it.
 *
 * Nothing merges on its own. A shared phone is strong evidence and still only
 * evidence — two families genuinely do share a handset — so candidates are
 * proposed and a person decides.
 */
class GuardianMergeService
{
    /**
     * Groups of guardians that look like the same person.
     *
     * Grouped by normalised phone, then by name where no phone is on file, so
     * a parent recorded twice without a number is still offered.
     *
     * @return Collection<int, array{key: string, reason: string, guardians: Collection<int, Guardian>}>
     */
    public function candidates(): Collection
    {
        $guardians = Guardian::with(['students:id,first_name,last_name,student_id'])->get();

        $byPhone = $guardians
            ->groupBy(fn (Guardian $g) => GuardianResolver::normalisePhone($g->primary_phone) ?? '')
            ->filter(fn (Collection $group, string $key) => $key !== '' && $group->count() > 1)
            ->map(fn (Collection $group, string $key) => [
                'key' => 'phone:'.$key,
                'reason' => __('Same phone number'),
                'guardians' => $group->sortBy('id')->values(),
            ]);

        $alreadyGrouped = $byPhone->flatMap(fn (array $g) => $g['guardians']->pluck('id'))->flip();

        $byName = $guardians
            ->reject(fn (Guardian $g) => $alreadyGrouped->has($g->id))
            ->groupBy(fn (Guardian $g) => mb_strtolower(trim($g->display_name)))
            ->filter(fn (Collection $group, string $key) => $key !== '' && $group->count() > 1
                && $key !== mb_strtolower(__('Parent / Guardian')))
            ->map(fn (Collection $group, string $key) => [
                'key' => 'name:'.$key,
                'reason' => __('Same name'),
                'guardians' => $group->sortBy('id')->values(),
            ]);

        return $byPhone->values()->concat($byName->values())
            ->sortByDesc(fn (array $group) => $group['guardians']->count())
            ->values();
    }

    /**
     * Join several guardian records into one.
     *
     * The record kept is the one that can already sign in where there is one,
     * because a parent who has set a password should not have to do it again.
     * Everything else moves to it: children, payment submissions, PTA levy
     * payments, and any contact detail the keeper was missing.
     *
     * @param  array<int, int>  $mergeIds  the records to fold in
     * @return array{children: int, submissions: int, details: int}
     */
    public function merge(Guardian $keeper, array $mergeIds): array
    {
        $merging = Guardian::whereIn('id', $mergeIds)
            ->where('id', '!=', $keeper->id)
            ->get();

        if ($merging->isEmpty()) {
            return ['children' => 0, 'submissions' => 0, 'details' => 0];
        }

        return DB::transaction(function () use ($keeper, $merging) {
            $links = app(GuardianLinkService::class);
            $children = 0;
            $submissions = 0;

            foreach ($merging as $other) {
                foreach ($other->students()->get() as $student) {
                    $wasPrimary = (bool) $student->pivot->is_primary;

                    // syncWithoutDetaching so a child already linked to the
                    // keeper — siblings often are — is not duplicated.
                    $links->link(
                        $keeper,
                        $student,
                        relationship: $student->pivot->relationship,
                        primary: $wasPrimary ?: null,
                        canAccessPortal: (bool) $student->pivot->can_access_portal
                    );

                    $other->students()->detach($student->id);
                    $links->syncPrimaryColumn($student->fresh());
                    $children++;
                }

                $submissions += ParentPaymentSubmission::where('guardian_id', $other->id)
                    ->update(['guardian_id' => $keeper->id]);

                if (DB::getSchemaBuilder()->hasTable('pta_levy_payments')) {
                    DB::table('pta_levy_payments')
                        ->where('guardian_id', $other->id)
                        ->update(['guardian_id' => $keeper->id]);
                }
            }

            $details = $this->absorbDetails($keeper, $merging);

            Guardian::whereIn('id', $merging->pluck('id'))->delete();

            return ['children' => $children, 'submissions' => $submissions, 'details' => $details];
        });
    }

    /**
     * Take any contact detail the keeper is missing from the records folded in.
     *
     * Only fills blanks. The keeper's own details are never overwritten —
     * a merge should not be able to change what a parent's phone number is.
     *
     * @param  Collection<int, Guardian>  $merging
     * @return int fields filled
     */
    private function absorbDetails(Guardian $keeper, Collection $merging): int
    {
        $fields = [
            'guardian_name', 'guardian_relationship', 'guardian_phone', 'guardian_email',
            'father_name', 'father_phone', 'father_email', 'father_occupation', 'father_address',
            'mother_name', 'mother_phone', 'mother_email', 'mother_occupation', 'mother_address',
            'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone',
            'login_email',
        ];

        $filled = 0;

        foreach ($fields as $field) {
            if (filled($keeper->getAttribute($field))) {
                continue;
            }

            $value = $merging->map(fn (Guardian $g) => $g->getAttribute($field))->first(fn ($v) => filled($v));

            if ($value !== null) {
                $keeper->setAttribute($field, $value);
                $filled++;
            }
        }

        if ($filled > 0) {
            $keeper->save();
        }

        return $filled;
    }

    /**
     * Which record a group should keep, if nobody says otherwise.
     *
     * One that can already sign in, else the one with the most children, else
     * the oldest — in that order, because losing a working login costs the
     * parent the most.
     *
     * @param  Collection<int, Guardian>  $group
     */
    public function suggestKeeper(Collection $group): Guardian
    {
        return $group->sortByDesc(fn (Guardian $g) => [
            $g->canAccessPortal() ? 1 : 0,
            $g->students->count(),
            -$g->id,
        ])->first();
    }
}
