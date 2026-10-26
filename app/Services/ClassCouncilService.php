<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ClassCouncil;
use App\Models\ClassCouncilEntry;
use App\Models\ClassSection;
use App\Models\SchoolSetting;
use App\Models\Term;
use App\Models\TermResult;
use App\Support\BranchContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Opening, filling and closing a class council.
 *
 * The council is the act that turns a term's marks into decisions the school
 * stands behind. Everything here keeps one rule: the system may suggest, the
 * council decides. An award is never written by a threshold alone — it is
 * pre-filled and then confirmed or overridden by the people in the room,
 * which is the only reason to hold a meeting rather than run a query.
 */
class ClassCouncilService
{
    /**
     * Where the suggestions sit, against the term average.
     *
     * Deliberately generous at the top and tied to the school's own pass mark
     * at the bottom, so a school that marks out of 20 and one that marks out
     * of 100 both get sensible starting points.
     */
    private const SUGGESTIONS = [
        ClassCouncilEntry::CONGRATULATIONS => 0.80,  // 16/20
        ClassCouncilEntry::HONOUR_ROLL => 0.70,      // 14/20
        ClassCouncilEntry::ENCOURAGEMENT => 0.60,    // 12/20
    ];

    /**
     * Open the council for a class and term, or return the one already open.
     *
     * Opening is idempotent: a second attempt returns the existing sitting
     * rather than starting a rival one, and tops up entries for pupils whose
     * results were published after it was opened.
     */
    public function open(ClassSection $section, Term $term): ClassCouncil
    {
        return DB::transaction(function () use ($section, $term) {
            $council = ClassCouncil::where('class_section_id', $section->id)
                ->where('term_id', $term->id)
                ->first();

            if (! $council) {
                $branchId = BranchContext::current();

                $council = ClassCouncil::create([
                    'class_section_id' => $section->id,
                    'term_id' => $term->id,
                    'academic_session_id' => AcademicSession::current()?->id,
                    'reference' => ClassCouncil::nextReference($branchId),
                    'meeting_date' => now()->toDateString(),
                    'status' => ClassCouncil::OPEN,
                    'created_by' => auth()->id(),
                ]);
            }

            if ($council->isOpen()) {
                $this->syncEntries($council);
            }

            return $council->fresh(['entries.student', 'classSection', 'term']);
        });
    }

    /**
     * Give every pupil with a published result an entry, pre-filled.
     *
     * Only published results: a council deliberating on figures the school
     * has not finished checking is deliberating on nothing.
     */
    public function syncEntries(ClassCouncil $council): int
    {
        $results = $this->publishedResults($council);
        $existing = $council->entries()->pluck('student_id')->all();
        $created = 0;

        foreach ($results as $result) {
            $studentId = $result->enrollment?->student_id;

            if (! $studentId || in_array($studentId, $existing, true)) {
                continue;
            }

            ClassCouncilEntry::create([
                'class_council_id' => $council->id,
                'student_id' => $studentId,
                'term_result_id' => $result->id,
                'average' => $result->term_average,
                'rank' => $result->class_rank,
                'award' => $this->suggestAward((float) $result->term_average),
                'conduct' => $result->conduct,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * The award the figures point at, before anyone has discussed the pupil.
     *
     * Below the pass mark is a work warning rather than nothing, because a
     * council that says nothing about a failing pupil has not deliberated.
     */
    public function suggestAward(?float $average): string
    {
        if ($average === null) {
            return ClassCouncilEntry::NONE;
        }

        $max = (float) (SchoolSetting::current()?->max_mark ?: 20);
        $pass = (float) (SchoolSetting::current()?->pass_mark ?: 10);

        if ($average < $pass) {
            return ClassCouncilEntry::WORK_WARNING;
        }

        foreach (self::SUGGESTIONS as $award => $share) {
            if ($average >= $max * $share) {
                return $award;
            }
        }

        return ClassCouncilEntry::NONE;
    }

    /**
     * Live statistics for an open council.
     *
     * @return array<string, mixed>
     */
    public function statistics(ClassCouncil $council): array
    {
        $averages = $council->entries->pluck('average')->filter()->map(fn ($v) => (float) $v);
        $pass = (float) (SchoolSetting::current()?->pass_mark ?: 10);
        $passed = $averages->filter(fn ($a) => $a >= $pass)->count();

        return [
            'students' => $council->entries->count(),
            'passed' => $passed,
            'failed' => max(0, $averages->count() - $passed),
            'class_average' => $averages->isNotEmpty() ? round($averages->avg(), 2) : null,
            'pass_rate' => $averages->count() > 0 ? round(($passed / $averages->count()) * 100, 2) : null,
            'highest_average' => $averages->max(),
            'lowest_average' => $averages->min(),
        ];
    }

    /** @return array<string, int> award => count */
    public function awardTally(ClassCouncil $council): array
    {
        $tally = array_fill_keys(array_keys(ClassCouncilEntry::awards()), 0);

        foreach ($council->entries as $entry) {
            $tally[$entry->award] = ($tally[$entry->award] ?? 0) + 1;
        }

        return $tally;
    }

    /**
     * Close the council, freezing what it saw onto the record.
     *
     * After this the minutes stay true even if a mark is corrected next week,
     * which is the whole reason the figures are copied rather than joined.
     */
    public function conclude(ClassCouncil $council): ClassCouncil
    {
        $council->update($this->statistics($council) + [
            'status' => ClassCouncil::CONCLUDED,
            'concluded_at' => now(),
            'concluded_by' => auth()->id(),
        ]);

        return $council->fresh();
    }

    /** Reopen a concluded council so a decision can be corrected. */
    public function reopen(ClassCouncil $council): ClassCouncil
    {
        $council->update([
            'status' => ClassCouncil::OPEN,
            'concluded_at' => null,
            'concluded_by' => null,
        ]);

        return $council->fresh();
    }

    /** @return Collection<int, TermResult> */
    private function publishedResults(ClassCouncil $council): Collection
    {
        return TermResult::query()
            ->where('term_results.term_id', $council->term_id)
            ->where('term_results.is_published', true)
            ->whereHas('enrollment', fn ($q) => $q->where('class_section_id', $council->class_section_id))
            ->with('enrollment.student')
            ->get();
    }
}
