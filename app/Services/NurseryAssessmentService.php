<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\NurseryAssessment;
use App\Models\NurseryCompetence;
use App\Models\NurseryLearningArea;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\TermResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Competence-based assessment for nursery classes.
 *
 * A nursery child is observed, not examined. The teacher goes down a list of
 * competences and says, for each, whether the child has acquired it, is on
 * the way, or has not got there yet. This service owns that list, the
 * ratings, and the term report built from them. There is no arithmetic in it
 * on purpose: nothing here averages, weights or ranks.
 */
class NurseryAssessmentService
{
    public static function isNursery(?Form $form): bool
    {
        return $form?->level === 'nursery';
    }

    // ---------------------------------------------------------------- catalogue

    /**
     * The competences a class is assessed against, grouped by learning area,
     * in display order. Shared competences and the class's own, active only.
     *
     * @return Collection<int, NurseryLearningArea> each with ->competences loaded
     */
    public function competencesFor(Form $form): Collection
    {
        return NurseryLearningArea::query()
            ->where('is_active', true)
            ->ordered()
            ->with(['competences' => fn ($q) => $q->where('is_active', true)->forForm($form->id)])
            ->get()
            ->filter(fn ($area) => $area->competences->isNotEmpty())
            ->values();
    }

    public function catalogueIsEmpty(): bool
    {
        return ! NurseryLearningArea::query()->exists();
    }

    /**
     * Load the standard list — the learning areas of the Cameroon preschool
     * curriculum and the competences most nursery schools report on. It is a
     * starting point to edit, not a rule.
     *
     * @return int competences created
     */
    public function seedDefaults(): int
    {
        $created = 0;

        DB::transaction(function () use (&$created) {
            foreach (self::standardCatalogue() as $order => [$areaName, $items]) {
                $area = NurseryLearningArea::create(['name' => $areaName, 'display_order' => $order + 1]);

                foreach ($items as $i => $description) {
                    $area->competences()->create(['description' => $description, 'display_order' => $i + 1]);
                    $created++;
                }
            }
        });

        return $created;
    }

    /** @return array<int, array{0: string, 1: array<int, string>}> */
    public static function standardCatalogue(): array
    {
        return [
            [__('Language and communication'), [
                __('Listens to a story and answers questions about it'),
                __('Speaks in complete sentences'),
                __('Recites rhymes, poems and songs'),
                __('Recognises and names the letters of the alphabet'),
                __('Writes own first name'),
            ]],
            [__('Pre-mathematics'), [
                __('Counts objects up to ten'),
                __('Recognises and writes the numerals 1 to 10'),
                __('Names the basic shapes: circle, square, triangle, rectangle'),
                __('Sorts objects by colour, size and shape'),
                __('Compares quantities: more, less, the same'),
            ]],
            [__('Discovery of the environment'), [
                __('Names the parts of the body'),
                __('Names common animals and plants'),
                __('Knows the days of the week'),
                __('Tells day from night and names the weather'),
                __('Knows own full name and the name of the school'),
            ]],
            [__('Psychomotor skills'), [
                __('Runs, jumps and hops with balance'),
                __('Holds a pencil correctly and traces lines'),
                __('Cuts along a line with scissors'),
                __('Threads beads and builds with blocks'),
                __('Dresses and undresses without help'),
            ]],
            [__('Personal and social development'), [
                __('Shares and takes turns'),
                __('Follows class rules and routines'),
                __('Plays and works with other children'),
                __('Says how they feel in words'),
                __('Keeps self and belongings clean and tidy'),
            ]],
            [__('Art, music and creativity'), [
                __('Draws and colours within the lines'),
                __('Sings and moves to music'),
                __('Models shapes with clay or plasticine'),
            ]],
        ];
    }

    // ---------------------------------------------------------------- the class

    /**
     * Every active pupil of a nursery class with how far their sheet has got.
     *
     * @return array{total: int, pupils: Collection<int, array<string, mixed>>}
     */
    public function progress(ClassSection $classSection, Term $term, int $sessionId): array
    {
        $areas = $this->competencesFor($classSection->form);
        $total = $areas->sum(fn ($a) => $a->competences->count());
        $competenceIds = $areas->flatMap(fn ($a) => $a->competences->pluck('id'))->all();

        $enrollments = StudentEnrollment::query()
            ->where('class_section_id', $classSection->id)
            ->where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name.' '.$e->student->first_name)
            ->values();

        $rated = NurseryAssessment::query()
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->where('term_id', $term->id)
            ->whereIn('nursery_competence_id', $competenceIds)
            ->selectRaw('student_enrollment_id, count(*) as n, max(updated_at) as last')
            ->groupBy('student_enrollment_id')
            ->get()
            ->keyBy('student_enrollment_id');

        $reports = TermResult::query()
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->where('term_id', $term->id)
            ->get()
            ->keyBy('student_enrollment_id');

        $pupils = $enrollments->map(fn ($e) => [
            'enrollment' => $e,
            'student' => $e->student,
            'rated' => (int) ($rated[$e->id]->n ?? 0),
            'last' => $rated[$e->id]->last ?? null,
            'report' => $reports[$e->id] ?? null,
        ]);

        return ['total' => $total, 'pupils' => $pupils];
    }

    // ---------------------------------------------------------------- the pupil

    /**
     * One pupil's sheet for a term: the competences, what has been said so
     * far, the remarks, and attendance pre-filled from the register.
     *
     * @return array<string, mixed>
     */
    public function sheet(StudentEnrollment $enrollment, Term $term): array
    {
        $enrollment->loadMissing('student', 'classSection.form', 'classSection.classTeacher');

        $areas = $this->competencesFor($enrollment->classSection->form);

        $ratings = NurseryAssessment::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->where('term_id', $term->id)
            ->pluck('rating', 'nursery_competence_id')
            ->all();

        $report = TermResult::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->where('term_id', $term->id)
            ->first();

        $attendance = $this->attendance($enrollment, $term);

        return [
            'areas' => $areas,
            'total' => $areas->sum(fn ($a) => $a->competences->count()),
            'ratings' => $ratings,
            'report' => $report,
            'days_present' => $report?->days_present ?? $attendance['present'],
            'days_absent' => $report?->days_absent ?? $attendance['absent'],
            'from_register' => $report === null && ($attendance['present'] + $attendance['absent']) > 0,
        ];
    }

    /**
     * Record the sheet. Ratings are upserted, so a teacher can come back and
     * change their mind; a competence left blank stays blank. The term report
     * row is created on first save and never carries an average.
     *
     * @param  array<int, string>  $ratings  competence id => rating
     * @return int ratings recorded
     */
    public function save(StudentEnrollment $enrollment, Term $term, array $ratings, array $report, ?int $userId): int
    {
        $enrollment->loadMissing('classSection.form');

        $allowed = $this->competencesFor($enrollment->classSection->form)
            ->flatMap(fn ($a) => $a->competences->pluck('id'))
            ->flip();

        $count = 0;

        DB::transaction(function () use ($enrollment, $term, $ratings, $report, $userId, $allowed, &$count) {
            foreach ($ratings as $competenceId => $rating) {
                if (! isset($allowed[(int) $competenceId]) || ! in_array($rating, NurseryAssessment::RATINGS, true)) {
                    continue;
                }

                NurseryAssessment::updateOrCreate(
                    [
                        'student_enrollment_id' => $enrollment->id,
                        'term_id' => $term->id,
                        'nursery_competence_id' => (int) $competenceId,
                    ],
                    ['rating' => $rating, 'assessed_by' => $userId, 'branch_id' => $enrollment->branch_id],
                );
                $count++;
            }

            $row = TermResult::firstOrNew([
                'student_enrollment_id' => $enrollment->id,
                'term_id' => $term->id,
            ]);
            $row->fill([
                'branch_id' => $row->branch_id ?? $enrollment->branch_id,
                'class_teacher_remark' => $report['class_teacher_remark'] ?? $row->class_teacher_remark,
                'principal_remark' => $report['principal_remark'] ?? $row->principal_remark,
                'days_present' => $report['days_present'] ?? $row->days_present,
                'days_absent' => $report['days_absent'] ?? $row->days_absent,
            ]);
            $row->total_school_days = (int) $row->days_present + (int) $row->days_absent;
            $row->save();
        });

        return $count;
    }

    // ---------------------------------------------------------------- the card

    /**
     * What the report prints for one term result: areas with each competence's
     * rating, and the counts, so the card can say "18 of 27 acquired" instead
     * of an average.
     *
     * @return array<string, mixed>
     */
    public function card(TermResult $result): array
    {
        $result->loadMissing('enrollment.classSection.form', 'term');

        $areas = $this->competencesFor($result->enrollment->classSection->form);

        $ratings = NurseryAssessment::query()
            ->where('student_enrollment_id', $result->student_enrollment_id)
            ->where('term_id', $result->term_id)
            ->pluck('rating', 'nursery_competence_id')
            ->all();

        $counts = array_fill_keys(NurseryAssessment::RATINGS, 0) + ['unrated' => 0];

        foreach ($areas as $area) {
            foreach ($area->competences as $competence) {
                $rating = $ratings[$competence->id] ?? null;
                $competence->setAttribute('rating', $rating);
                $counts[$rating ?? 'unrated']++;
            }
        }

        // Earlier terms' ratings, so a parent sees movement, not a snapshot.
        $previous = NurseryAssessment::query()
            ->where('student_enrollment_id', $result->student_enrollment_id)
            ->where('term_id', '!=', $result->term_id)
            ->whereHas('term', fn ($q) => $q->where('term_number', '<', $result->term->term_number))
            ->with('term')
            ->get()
            ->groupBy('nursery_competence_id')
            ->map(fn ($rows) => $rows->sortByDesc(fn ($r) => $r->term->term_number)->first()->rating);

        return [
            'areas' => $areas,
            'counts' => $counts,
            'total' => array_sum($counts),
            'previous' => $previous->all(),
        ];
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{present: int, absent: int} */
    private function attendance(StudentEnrollment $enrollment, Term $term): array
    {
        $rows = Attendance::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->whereDate('date', '>=', $term->start_date)
            ->whereDate('date', '<=', $term->end_date)
            ->pluck('status');

        return [
            'present' => $rows->filter(fn ($s) => in_array($s, ['present', 'late'], true))->count(),
            'absent' => $rows->filter(fn ($s) => $s === 'absent')->count(),
        ];
    }
}
