<?php

namespace App\Services;

use App\Models\ClassSection;
use App\Models\GradeScale;
use App\Models\Mark;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\SubjectTermResult;
use App\Models\Term;
use App\Models\TermResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the term's marks say about the school.
 *
 * The school already stores everything needed to answer this — term averages,
 * ranks, per-subject results, the teacher who taught each one — and reads none
 * of it back. The only screen that looked at results showed a single chart of
 * class averages.
 *
 * Everything here is computed from published results rather than stored, so it
 * cannot drift from what the report cards say. That also means it is only as
 * complete as mark entry is, which is why `coverage()` is reported alongside
 * the figures: an 80% pass rate drawn from a third of the subjects is not an
 * 80% pass rate, and a screen that does not say so is lying quietly.
 */
class AcademicAnalyticsService
{
    private float $passMark;

    public function __construct()
    {
        $this->passMark = (float) (SchoolSetting::current()?->pass_mark ?? 10);
    }

    /**
     * The whole report for one term, optionally narrowed to a form or a class.
     *
     * @return array<string, mixed>
     */
    public function report(int $termId, ?int $formId = null, ?int $classSectionId = null): array
    {
        $results = $this->termResults($termId, $formId, $classSectionId);

        return [
            'pass_mark' => $this->passMark,
            'headline' => $this->headline($results),
            'bands' => $this->bands($results),
            'classes' => $this->byClass($results),
            'subjects' => $this->bySubject($results),
            'teachers' => $this->byTeacher($results),
            'at_risk' => $this->atRisk($results, $termId),
            'sequence_trend' => $this->sequenceTrend($termId, $formId, $classSectionId),
            'term_trend' => $this->termTrend($termId, $formId, $classSectionId),
            'coverage' => $this->coverage($termId, $formId, $classSectionId),
        ];
    }

    /**
     * Published term results in scope, with everything the report needs.
     *
     * Only published results count. A draft result is the school's working
     * copy, and averaging it into a pass rate would report a figure nobody
     * has stood behind yet.
     */
    private function termResults(int $termId, ?int $formId, ?int $classSectionId): Collection
    {
        return TermResult::query()
            ->where('term_results.term_id', $termId)
            ->where('term_results.is_published', true)
            ->whereHas('enrollment.classSection.form', fn ($q) => $q->forCurrentLevel())
            ->when($classSectionId, fn ($q) => $q->whereHas('enrollment', fn ($e) => $e->where('class_section_id', $classSectionId)))
            ->when($formId && ! $classSectionId, fn ($q) => $q->whereHas('enrollment.classSection', fn ($c) => $c->where('form_id', $formId)))
            ->with([
                'enrollment.student:id,first_name,last_name,student_id',
                'enrollment.classSection:id,name,form_id',
                'enrollment.classSection.form:id,name',
                'subjectResults.subject:id,name',
            ])
            ->get();
    }

    /** @return array<string, mixed> */
    private function headline(Collection $results): array
    {
        $averages = $results->pluck('term_average')->filter()->map(fn ($v) => (float) $v);

        return [
            'students' => $results->count(),
            'average' => $averages->isNotEmpty() ? round($averages->avg(), 2) : null,
            'pass_rate' => $this->rate($averages->filter(fn ($a) => $a >= $this->passMark)->count(), $averages->count()),
            'highest' => $averages->max(),
            'lowest' => $averages->min(),
        ];
    }

    /**
     * How many pupils sit in each grade band.
     *
     * Read from the school's own grade scale rather than invented bands, so
     * the distribution and the report cards agree on what a B is.
     *
     * @return array<int, array<string, mixed>>
     */
    private function bands(Collection $results): array
    {
        $scale = GradeScale::orderBy('display_order')->get();

        if ($scale->isEmpty()) {
            return [];
        }

        $averages = $results->pluck('term_average')->filter()->map(fn ($v) => (float) $v);

        return $scale->map(fn (GradeScale $band) => [
            'grade' => $band->grade,
            'description' => $band->description,
            'from' => (float) $band->min_mark,
            'to' => (float) $band->max_mark,
            'count' => $averages->filter(
                fn ($a) => $a >= (float) $band->min_mark && $a <= (float) $band->max_mark
            )->count(),
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function byClass(Collection $results): array
    {
        return $results
            ->groupBy(fn (TermResult $r) => $r->enrollment?->class_section_id)
            ->filter(fn ($group, $key) => $key !== null && $key !== '')
            ->map(function (Collection $group) {
                $averages = $group->pluck('term_average')->filter()->map(fn ($v) => (float) $v);
                $section = $group->first()->enrollment->classSection;

                return [
                    'id' => $section?->id,
                    'name' => $section?->name,
                    'form' => $section?->form?->name,
                    'students' => $group->count(),
                    'average' => $averages->isNotEmpty() ? round($averages->avg(), 2) : null,
                    'pass_rate' => $this->rate($averages->filter(fn ($a) => $a >= $this->passMark)->count(), $averages->count()),
                    'highest' => $averages->max(),
                    'lowest' => $averages->min(),
                ];
            })
            ->sortByDesc('average')
            ->values()
            ->all();
    }

    /**
     * Each subject's average and pass rate, and — the useful part — how it sits
     * against the pupils' own overall averages.
     *
     * A subject averaging 9 in a class averaging 9 is a hard class. A subject
     * averaging 9 in a class averaging 14 is a subject in trouble, and that is
     * the one a head of department needs to see. `delta` is that difference.
     *
     * @return array<int, array<string, mixed>>
     */
    private function bySubject(Collection $results): array
    {
        // The pupil's own overall average, to compare each subject against.
        $overallFor = $results->mapWithKeys(
            fn (TermResult $r) => [$r->id => (float) $r->term_average]
        );

        $rows = [];

        foreach ($results as $result) {
            foreach ($result->subjectResults as $subjectResult) {
                if ($subjectResult->term_average === null) {
                    continue;
                }

                $key = $subjectResult->subject_id;
                $rows[$key] ??= [
                    'id' => $key,
                    'name' => $subjectResult->subject?->name ?? __('Unknown subject'),
                    'scores' => [],
                    'deltas' => [],
                ];

                $score = (float) $subjectResult->term_average;
                $rows[$key]['scores'][] = $score;
                $rows[$key]['deltas'][] = $score - ($overallFor[$result->id] ?? $score);
            }
        }

        return collect($rows)
            ->map(function (array $row) {
                $scores = collect($row['scores']);
                $deltas = collect($row['deltas']);

                return [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'entries' => $scores->count(),
                    'average' => round($scores->avg(), 2),
                    'pass_rate' => $this->rate($scores->filter(fn ($s) => $s >= $this->passMark)->count(), $scores->count()),
                    'delta' => round($deltas->avg(), 2),
                    'highest' => $scores->max(),
                    'lowest' => $scores->min(),
                ];
            })
            ->sortBy('delta')
            ->values()
            ->all();
    }

    /**
     * The same figures per teacher.
     *
     * Taken from the teacher name frozen onto each subject result, which is
     * who actually taught it that term — reading the current assignment would
     * credit a teacher with a predecessor's marks.
     *
     * @return array<int, array<string, mixed>>
     */
    private function byTeacher(Collection $results): array
    {
        $rows = [];

        foreach ($results as $result) {
            foreach ($result->subjectResults as $subjectResult) {
                $teacher = trim((string) $subjectResult->teacher_name);

                if ($teacher === '' || $subjectResult->term_average === null) {
                    continue;
                }

                $rows[$teacher] ??= ['scores' => [], 'subjects' => []];
                $rows[$teacher]['scores'][] = (float) $subjectResult->term_average;
                $rows[$teacher]['subjects'][$subjectResult->subject_id] = $subjectResult->subject?->name;
            }
        }

        return collect($rows)
            ->map(function (array $row, string $teacher) {
                $scores = collect($row['scores']);

                return [
                    'teacher' => $teacher,
                    'subjects' => collect($row['subjects'])->filter()->values()->all(),
                    'entries' => $scores->count(),
                    'average' => round($scores->avg(), 2),
                    'pass_rate' => $this->rate($scores->filter(fn ($s) => $s >= $this->passMark)->count(), $scores->count()),
                ];
            })
            ->sortByDesc('average')
            ->values()
            ->all();
    }

    /**
     * Pupils who need attention: below the pass mark, or falling.
     *
     * Falling matters on its own. A pupil who drops from 15 to 11 is still
     * passing and will never appear on a threshold list, but something has
     * happened to them that a term average alone does not say.
     *
     * @return array<int, array<string, mixed>>
     */
    private function atRisk(Collection $results, int $termId): array
    {
        $previous = $this->previousAverages($results, $termId);

        return $results
            ->map(function (TermResult $result) use ($previous) {
                $average = (float) $result->term_average;
                $before = $previous[$result->enrollment?->student_id] ?? null;
                $change = $before !== null ? round($average - $before, 2) : null;

                $failing = $average < $this->passMark;
                // Two marks is a real slide, not the noise of a hard paper.
                $falling = $change !== null && $change <= -2;

                if (! $failing && ! $falling) {
                    return null;
                }

                return [
                    'student' => trim(($result->enrollment?->student?->first_name ?? '').' '.($result->enrollment?->student?->last_name ?? '')),
                    'student_id' => $result->enrollment?->student?->student_id,
                    'class' => $result->enrollment?->classSection?->name,
                    'average' => round($average, 2),
                    'previous' => $before,
                    'change' => $change,
                    'rank' => $result->class_rank,
                    'reason' => match (true) {
                        $failing && $falling => __('Below the pass mark and falling'),
                        $failing => __('Below the pass mark'),
                        default => __('Falling'),
                    },
                ];
            })
            ->filter()
            ->sortBy('average')
            ->values()
            ->all();
    }

    /**
     * Each pupil's average in the term before this one, for the fall check.
     *
     * @return array<int, float> keyed by student id
     */
    private function previousAverages(Collection $results, int $termId): array
    {
        $term = Term::find($termId);

        if (! $term || $term->term_number <= 1) {
            return [];
        }

        $previousTerm = Term::where('term_number', $term->term_number - 1)->first();

        if (! $previousTerm) {
            return [];
        }

        $studentIds = $results->pluck('enrollment.student_id')->filter()->unique();

        return TermResult::query()
            ->where('term_results.term_id', $previousTerm->id)
            ->where('term_results.is_published', true)
            ->join('student_enrollments', 'term_results.student_enrollment_id', '=', 'student_enrollments.id')
            ->whereIn('student_enrollments.student_id', $studentIds)
            ->pluck('term_results.term_average', 'student_enrollments.student_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * The average over the term's sequences, from the marks themselves.
     *
     * Read from marks rather than from the stored sequence columns on the
     * subject result, because a school may run more sequences than the three
     * those columns hold.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sequenceTrend(int $termId, ?int $formId, ?int $classSectionId): array
    {
        $sequences = Sequence::forTerm($termId, $this->formsInScope($formId, $classSectionId));

        if ($sequences->isEmpty()) {
            return [];
        }

        $rows = Mark::query()
            ->whereIn('marks.sequence_id', $sequences->pluck('id'))
            ->where('marks.status', 'published')
            ->where('marks.is_absent', false)
            ->join('student_enrollments', 'marks.student_enrollment_id', '=', 'student_enrollments.id')
            ->join('class_sections', 'student_enrollments.class_section_id', '=', 'class_sections.id')
            ->when($classSectionId, fn ($q) => $q->where('student_enrollments.class_section_id', $classSectionId))
            ->when($formId && ! $classSectionId, fn ($q) => $q->where('class_sections.form_id', $formId))
            ->select('marks.sequence_id', DB::raw('AVG(marks.score) as average'), DB::raw('COUNT(*) as entries'))
            ->groupBy('marks.sequence_id')
            ->pluck('average', 'marks.sequence_id');

        return $sequences->map(fn (Sequence $sequence) => [
            'label' => $sequence->name,
            'average' => isset($rows[$sequence->id]) ? round((float) $rows[$sequence->id], 2) : null,
        ])->values()->all();
    }

    /**
     * The same across the session's terms, so a term reads in context.
     *
     * @return array<int, array<string, mixed>>
     */
    private function termTrend(int $termId, ?int $formId, ?int $classSectionId): array
    {
        return Term::orderBy('term_number')->get()->map(function (Term $term) use ($formId, $classSectionId) {
            $average = TermResult::query()
                ->where('term_results.term_id', $term->id)
                ->where('term_results.is_published', true)
                ->join('student_enrollments', 'term_results.student_enrollment_id', '=', 'student_enrollments.id')
                ->join('class_sections', 'student_enrollments.class_section_id', '=', 'class_sections.id')
                ->when($classSectionId, fn ($q) => $q->where('student_enrollments.class_section_id', $classSectionId))
                ->when($formId && ! $classSectionId, fn ($q) => $q->where('class_sections.form_id', $formId))
                ->avg('term_results.term_average');

            return [
                'label' => $term->name,
                'average' => $average !== null ? round((float) $average, 2) : null,
            ];
        })->values()->all();
    }

    /**
     * How much of the term these figures actually rest on.
     *
     * Without this the report looks equally confident whether every subject
     * has reported or only two have.
     *
     * @return array<string, mixed>
     */
    private function coverage(int $termId, ?int $formId, ?int $classSectionId): array
    {
        $enrollments = DB::table('student_enrollments')
            ->join('class_sections', 'student_enrollments.class_section_id', '=', 'class_sections.id')
            ->where('student_enrollments.status', 'active')
            ->when($classSectionId, fn ($q) => $q->where('student_enrollments.class_section_id', $classSectionId))
            ->when($formId && ! $classSectionId, fn ($q) => $q->where('class_sections.form_id', $formId))
            ->count();

        $published = TermResult::query()
            ->where('term_results.term_id', $termId)
            ->where('term_results.is_published', true)
            ->join('student_enrollments', 'term_results.student_enrollment_id', '=', 'student_enrollments.id')
            ->join('class_sections', 'student_enrollments.class_section_id', '=', 'class_sections.id')
            ->when($classSectionId, fn ($q) => $q->where('student_enrollments.class_section_id', $classSectionId))
            ->when($formId && ! $classSectionId, fn ($q) => $q->where('class_sections.form_id', $formId))
            ->count();

        return [
            'enrolled' => $enrollments,
            'published' => $published,
            'percent' => $this->rate($published, $enrollments),
        ];
    }

    /**
     * The forms the current filter covers, for looking up their sequences.
     *
     * @return array<int, int>
     */
    private function formsInScope(?int $formId, ?int $classSectionId): array
    {
        if ($classSectionId) {
            return ClassSection::where('id', $classSectionId)->pluck('form_id')->all();
        }

        if ($formId) {
            return [$formId];
        }

        return ClassSection::where('is_active', true)
            ->whereHas('form', fn ($q) => $q->forCurrentLevel())
            ->distinct()->pluck('form_id')->all();
    }

    private function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : null;
    }

    /**
     * The subject table as CSV rows, for the export.
     *
     * @param  array<string, mixed>  $report
     * @return array<int, array<int, string|float|null>>
     */
    public function subjectRows(array $report): array
    {
        $rows = [[
            __('Subject'), __('Entries'), __('Average'), __('Pass rate %'),
            __('Against pupil average'), __('Highest'), __('Lowest'),
        ]];

        foreach ($report['subjects'] as $subject) {
            $rows[] = [
                $subject['name'], $subject['entries'], $subject['average'],
                $subject['pass_rate'], $subject['delta'], $subject['highest'], $subject['lowest'],
            ];
        }

        return $rows;
    }
}
