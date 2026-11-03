<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\ClassSection;
use App\Models\GradeScale;
use App\Models\RecordedSheet;
use App\Models\StudentEnrollment;
use App\Models\SubjectTermResult;
use App\Models\Term;
use App\Models\TermResult;
use App\Support\SchoolCsv;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Last year's results, copied from the school's own mark sheets.
 *
 * The figures that went home on the report cards are the record: an average
 * or a rank written on the sheet is kept as written, never replaced by a
 * recomputation. Where the sheet gives only the subject marks, the term
 * average and rank are worked out the way the school works them out here
 * (coefficient-weighted, ties sharing a place), and the verifier sees where a
 * written average disagrees with its own marks.
 *
 * Marks are stored on the school's scale; a sheet kept out of 10 or 100 is
 * converted once, on the way in.
 */
class RecordedResults
{
    private const CONDUCT = [
        'excellent' => 'excellent', 'excellente' => 'excellent',
        'very_good' => 'very_good', 'very good' => 'very_good', 'tres bien' => 'very_good', 'très bien' => 'very_good', 'tb' => 'very_good',
        'good' => 'good', 'bien' => 'good', 'bonne' => 'good', 'b' => 'good',
        'fair' => 'fair', 'assez bien' => 'fair', 'passable' => 'fair', 'ab' => 'fair', 'average' => 'fair',
        'poor' => 'poor', 'mauvaise' => 'poor', 'mauvais' => 'poor', 'insuffisant' => 'poor', 'bad' => 'poor',
    ];

    private const ALIASES = [
        'student_id' => 'matricule', 'mat' => 'matricule', 'matricule_no' => 'matricule',
        'nom' => 'name', 'noms' => 'name', 'names' => 'name', 'full_name' => 'name', 'pupil' => 'name', 'eleve' => 'name', 'élève' => 'name',
        'moyenne' => 'average', 'avg' => 'average', 'term_average' => 'average', 'moy' => 'average',
        'rang' => 'rank', 'position' => 'rank', 'pos' => 'rank',
        'conduite' => 'conduct',
        'absences' => 'days_absent', 'absent' => 'days_absent', 'days_absent' => 'days_absent',
        'observation' => 'remark', 'appreciation' => 'remark', 'appréciation' => 'remark', 'remarks' => 'remark', 'teacher_remark' => 'remark',
        'moyenne_annuelle' => 'annual_average', 'annual_avg' => 'annual_average', 'year_average' => 'annual_average',
        'rang_annuel' => 'annual_rank', 'year_rank' => 'annual_rank', 'annual_position' => 'annual_rank',
    ];

    public function __construct(private PastYearRecorder $years)
    {
    }

    public function sheet(AcademicSession $year, ClassSection $section, Term $term): RecordedSheet
    {
        $this->years->assertRecorded($year);

        return RecordedSheet::firstOrCreate(
            ['academic_session_id' => $year->id, 'class_section_id' => $section->id, 'term_id' => $term->id],
            ['status' => 'draft', 'out_of' => 20]
        );
    }

    /** @return Collection<int, object{id:int,code:?string,name:string,coefficient:float}> */
    public function subjects(ClassSection $section): Collection
    {
        return DB::table('form_subject')
            ->join('subjects', 'subjects.id', '=', 'form_subject.subject_id')
            ->where('form_subject.form_id', $section->form_id)
            ->orderBy('subjects.name')
            ->get(['subjects.id', 'subjects.code', 'subjects.name', 'form_subject.coefficient'])
            ->unique('id')
            ->values();
    }

    /** @return Collection<int, StudentEnrollment> the pupils recorded in this class and term */
    public function enrollments(RecordedSheet $sheet): Collection
    {
        return StudentEnrollment::with('student')
            ->where('academic_session_id', $sheet->academic_session_id)
            ->where('class_section_id', $sheet->class_section_id)
            ->where('term_id', $sheet->term_id)
            ->get()
            ->sortBy(fn ($e) => [$e->student?->last_name, $e->student?->first_name])
            ->values();
    }

    public function isLastTerm(Term $term): bool
    {
        return (int) $term->term_number === (int) Term::max('term_number');
    }

    /** @return array{0: list<string>, 1: list<list<string>>} the blank sheet, pupils filled in */
    public function template(RecordedSheet $sheet): array
    {
        $subjects = $this->subjects($sheet->classSection);
        $headers = array_merge(
            ['matricule', 'name'],
            $subjects->map(fn ($s) => $s->code ?: $s->name)->all(),
            ['average', 'rank', 'conduct', 'days_absent', 'remark'],
            $this->isLastTerm($sheet->term) ? ['annual_average', 'annual_rank'] : []
        );

        $rows = $this->enrollments($sheet)
            ->map(fn ($e) => array_pad([$e->student->student_id, trim($e->student->last_name.' '.$e->student->first_name)], count($headers), ''))
            ->all();

        return [$headers, $rows];
    }

    /**
     * Read a filled sheet in. Replaces whatever the sheet held: it is the
     * whole class for that term, so a corrected file is simply sent again.
     *
     * @return array{saved: int, errors: list<string>, warnings: list<string>}
     */
    public function import(RecordedSheet $sheet, string $path, float $outOf): array
    {
        if ($sheet->isVerified()) {
            throw new RuntimeException(__('This sheet is verified and locked. The head teacher can reopen it with a reason.'));
        }
        if ($outOf <= 0 || $outOf > 1000) {
            throw new RuntimeException(__('Say what the marks are out of (for example 20, 10 or 100).'));
        }

        [$headers, $rows] = SchoolCsv::read($path, self::ALIASES);
        if (! in_array('matricule', $headers, true)) {
            throw new RuntimeException(__('The file needs a matricule column. Download the template to see the layout.'));
        }

        $subjects = $this->subjects($sheet->classSection);
        $subjectColumns = [];
        $unknown = [];
        $known = ['matricule', 'name', 'average', 'rank', 'conduct', 'days_absent', 'remark', 'annual_average', 'annual_rank'];
        foreach ($headers as $header) {
            if (in_array($header, $known, true) || $header === '') {
                continue;
            }
            $subject = $subjects->first(fn ($s) => $this->key($s->code) === $header || $this->key($s->name) === $header);
            $subject ? $subjectColumns[$header] = $subject : $unknown[] = $header;
        }
        if ($subjectColumns === []) {
            throw new RuntimeException(__('No column matches a subject of :class. Use the subject codes or names, as in the template.', ['class' => $sheet->classSection->name]));
        }

        $enrollments = $this->enrollments($sheet)->keyBy(fn ($e) => mb_strtolower((string) $e->student?->student_id));
        $scale = (float) (\App\Models\SchoolSetting::current()?->max_mark ?: 20);
        $factor = $scale / $outOf;
        $errors = [];
        $warnings = $unknown ? [__('Columns not used (no such subject in :class): :list', ['class' => $sheet->classSection->name, 'list' => implode(', ', $unknown)])] : [];
        $parsed = [];

        foreach ($rows as $line => $row) {
            $r = SchoolCsv::combine($headers, $row);
            if (trim(implode('', $r)) === '') {
                continue;
            }
            $enrollment = $enrollments->get(mb_strtolower($r['matricule']));
            if (! $enrollment) {
                $errors[] = __('Line :line: :value was not in :class in :year. Place the pupil in this class first.', [
                    'line' => $line, 'value' => $r['matricule'], 'class' => $sheet->classSection->name, 'year' => $sheet->academicSession->name,
                ]);

                continue;
            }

            $marks = [];
            $bad = [];
            foreach ($subjectColumns as $column => $subject) {
                $raw = trim($r[$column] ?? '');
                if ($raw === '') {
                    continue;
                }
                $value = $this->number($raw);
                if ($value === null || $value < 0 || $value > $outOf) {
                    $bad[] = $subject->code ?: $subject->name;

                    continue;
                }
                $marks[$subject->id] = round($value * $factor, 2);
            }
            if ($bad) {
                $errors[] = __('Line :line (:name): marks not between 0 and :max in :list', [
                    'line' => $line, 'name' => $enrollment->student->full_name, 'max' => $outOf, 'list' => implode(', ', $bad),
                ]);

                continue;
            }

            $given = fn ($key) => ($v = $this->number($r[$key] ?? '')) !== null ? round($v * $factor, 2) : null;
            $parsed[$enrollment->id] = [
                'enrollment' => $enrollment,
                'marks' => $marks,
                'average' => $given('average'),
                'rank' => ($v = $this->number($r['rank'] ?? '')) !== null ? (int) $v : null,
                'conduct' => self::CONDUCT[mb_strtolower(trim($r['conduct'] ?? ''))] ?? null,
                'days_absent' => ($v = $this->number($r['days_absent'] ?? '')) !== null ? (int) $v : null,
                'remark' => trim($r['remark'] ?? '') ?: null,
                'annual_average' => $given('annual_average'),
                'annual_rank' => ($v = $this->number($r['annual_rank'] ?? '')) !== null ? (int) $v : null,
            ];
        }

        if ($errors !== []) {
            // All or nothing: half a class recorded is harder to put right
            // than a file sent again.
            return ['saved' => 0, 'errors' => $errors, 'warnings' => $warnings];
        }

        DB::transaction(function () use ($sheet, $subjects, $parsed, $outOf) {
            $this->write($sheet, $subjects, $parsed);
            if ($this->isLastTerm($sheet->term)) {
                $this->writeAnnual($sheet, $parsed);
            }
            $sheet->update(['out_of' => $outOf, 'entered_by' => auth()->id(), 'entered_at' => now()]);
            $this->annual($sheet->academicSession, $sheet->classSection);
        });

        return ['saved' => count($parsed), 'errors' => [], 'warnings' => $warnings];
    }

    /**
     * @param  array<int, array<string, mixed>>  $parsed  by enrolment id
     */
    private function write(RecordedSheet $sheet, Collection $subjects, array $parsed): void
    {
        $coefficients = $subjects->pluck('coefficient', 'id')->map(fn ($c) => (float) $c ?: 1.0);
        $grades = GradeScale::orderByDesc('min_mark')->get();
        $grade = fn (?float $v) => $v === null ? null : ($grades->first(fn ($g) => $v >= (float) $g->min_mark)?->grade);

        // Averages first: the written one where the sheet has it, otherwise
        // the coefficient-weighted mean of the marks.
        foreach ($parsed as &$p) {
            $weighted = 0.0;
            $coefTotal = 0.0;
            foreach ($p['marks'] as $subjectId => $mark) {
                $weighted += $mark * $coefficients[$subjectId];
                $coefTotal += $coefficients[$subjectId];
            }
            $p['weighted'] = round($weighted, 2);
            $p['coef'] = $coefTotal;
            $p['computed'] = $coefTotal > 0 ? round($weighted / $coefTotal, 2) : null;
            $p['final'] = $p['average'] ?? $p['computed'];
        }
        unset($p);

        $ranks = $this->rank(collect($parsed)->map(fn ($p) => $p['final']));
        $averages = collect($parsed)->pluck('final')->filter(fn ($v) => $v !== null);
        $ranked = $averages->count();

        // Replaces the sheet: anyone no longer on it loses their row.
        $enrollmentIds = $this->enrollments($sheet)->pluck('id');
        TermResult::whereIn('student_enrollment_id', $enrollmentIds)->where('term_id', $sheet->term_id)
            ->whereNotIn('student_enrollment_id', array_keys($parsed))->delete();

        foreach ($parsed as $enrollmentId => $p) {
            $result = TermResult::updateOrCreate(
                ['student_enrollment_id' => $enrollmentId, 'term_id' => $sheet->term_id],
                [
                    'branch_id' => $p['enrollment']->branch_id,
                    'total_weighted_score' => $p['weighted'],
                    'total_coefficient' => $p['coef'],
                    'term_average' => $p['final'],
                    'overall_grade' => $grade($p['final']),
                    'class_rank' => $p['rank'] ?? $ranks[$enrollmentId] ?? null,
                    'total_students' => $ranked,
                    'class_average' => $averages->isNotEmpty() ? round($averages->avg(), 2) : null,
                    'highest_average' => $averages->max(),
                    'lowest_average' => $averages->min(),
                    'days_absent' => $p['days_absent'],
                    'conduct' => $p['conduct'],
                    'class_teacher_remark' => $p['remark'],
                    'source' => 'recorded',
                    'recorded_by' => auth()->id(),
                    // Visible only once the head teacher has verified it.
                    'is_published' => false,
                ]
            );

            $result->subjectResults()->whereNotIn('subject_id', array_keys($p['marks']))->delete();
            foreach ($p['marks'] as $subjectId => $mark) {
                SubjectTermResult::updateOrCreate(
                    ['term_result_id' => $result->id, 'subject_id' => $subjectId],
                    [
                        'branch_id' => $result->branch_id,
                        'coefficient' => $coefficients[$subjectId],
                        'term_average' => $mark,
                        'weighted_score' => round($mark * $coefficients[$subjectId], 2),
                        'grade' => $grade($mark),
                    ]
                );
            }

        }

        // Subject ranks, as on a report card.
        foreach ($subjects as $subject) {
            $marks = collect($parsed)->map(fn ($p) => $p['marks'][$subject->id] ?? null);
            foreach ($this->rank($marks) as $enrollmentId => $rank) {
                SubjectTermResult::whereHas('termResult', fn ($q) => $q->where('student_enrollment_id', $enrollmentId)->where('term_id', $sheet->term_id))
                    ->where('subject_id', $subject->id)
                    ->update(['subject_rank' => $rank, 'subject_total_students' => $marks->filter(fn ($v) => $v !== null)->count()]);
            }
        }
    }

    /**
     * The year's figures written on the last term's sheet, kept as written
     * on the pupil's last enrolment row of the year.
     *
     * @param  array<int, array<string, mixed>>  $parsed
     */
    private function writeAnnual(RecordedSheet $sheet, array $parsed): void
    {
        foreach ($parsed as $p) {
            $last = $this->lastRow($sheet->academicSession, $p['enrollment']->student_id);
            $written = $p['annual_average'] !== null;
            $last?->update([
                'final_written' => $written,
                'final_average' => $written ? $p['annual_average'] : null,
                'final_rank' => $written ? $p['annual_rank'] : null,
            ]);
        }
    }

    /**
     * The year's average and position where the sheets did not give them:
     * the mean of the term averages, once every term is recorded, ranked
     * within the class. A written figure is never replaced.
     */
    public function annual(AcademicSession $year, ClassSection $section): void
    {
        $rows = StudentEnrollment::with('term')
            ->where('academic_session_id', $year->id)
            ->where('class_section_id', $section->id)
            ->get()
            ->groupBy('student_id');
        $termCount = Term::count();
        $results = TermResult::whereIn('student_enrollment_id', $rows->flatten()->pluck('id'))->get()->keyBy('student_enrollment_id');

        $averages = collect();
        foreach ($rows as $studentId => $enrollments) {
            $terms = $enrollments->map(fn ($e) => $results->get($e->id)?->term_average)->filter(fn ($v) => $v !== null);
            $averages[$studentId] = $terms->count() === $termCount ? round((float) $terms->avg(), 2) : null;
        }
        $ranks = $this->rank($averages);

        foreach ($rows as $studentId => $enrollments) {
            $last = $enrollments->sortBy(fn ($e) => $e->term?->term_number)->last();
            if ($last->final_written) {
                continue;
            }
            $last->update([
                'final_average' => $averages[$studentId],
                'final_rank' => $averages[$studentId] !== null ? ($ranks[$studentId] ?? null) : null,
            ]);
        }
    }

    private function lastRow(AcademicSession $year, int $studentId): ?StudentEnrollment
    {
        return StudentEnrollment::with('term')->where('academic_session_id', $year->id)->where('student_id', $studentId)
            ->get()->sortBy(fn ($e) => $e->term?->term_number)->last();
    }

    /**
     * Where a written average disagrees with its own marks by more than a
     * rounding: the verifier looks at those lines on the paper first.
     *
     * @return list<string>
     */
    public function discrepancies(RecordedSheet $sheet): array
    {
        $out = [];
        $results = TermResult::with(['enrollment.student'])
            ->whereIn('student_enrollment_id', $this->enrollments($sheet)->pluck('id'))
            ->where('term_id', $sheet->term_id)
            ->get();

        foreach ($results as $r) {
            if ((float) $r->total_coefficient <= 0 || $r->term_average === null) {
                continue;
            }
            $fromMarks = round((float) $r->total_weighted_score / (float) $r->total_coefficient, 2);
            if (abs($fromMarks - (float) $r->term_average) > 0.05) {
                $out[] = __(':name: the sheet says :given, the marks give :computed', [
                    'name' => $r->enrollment?->student?->full_name, 'given' => number_format((float) $r->term_average, 2), 'computed' => number_format($fromMarks, 2),
                ]);
            }
        }

        return $out;
    }

    public function verify(RecordedSheet $sheet): void
    {
        if ($sheet->isVerified()) {
            return;
        }
        if (! $sheet->entered_by) {
            throw new RuntimeException(__('Nothing has been recorded on this sheet yet.'));
        }
        // The point of the check is a second pair of eyes.
        if ((int) $sheet->entered_by === (int) auth()->id()) {
            throw new RuntimeException(__('You entered this sheet, so someone else must verify it against the paper.'));
        }

        DB::transaction(function () use ($sheet) {
            $sheet->update(['status' => 'verified', 'verified_by' => auth()->id(), 'verified_at' => now()]);
            TermResult::whereIn('student_enrollment_id', $this->enrollments($sheet)->pluck('id'))
                ->where('term_id', $sheet->term_id)
                ->update(['is_published' => true]);
        });
    }

    public function reopen(RecordedSheet $sheet, string $reason): void
    {
        DB::transaction(function () use ($sheet, $reason) {
            AuditLog::log('recorded_sheet_reopened', RecordedSheet::class, $sheet->id, ['status' => $sheet->status, 'verified_by' => $sheet->verified_by], ['reason' => $reason]);
            $sheet->update(['status' => 'draft', 'verified_by' => null, 'verified_at' => null]);
            TermResult::whereIn('student_enrollment_id', $this->enrollments($sheet)->pluck('id'))
                ->where('term_id', $sheet->term_id)
                ->update(['is_published' => false]);
        });
    }

    /**
     * One pupil's recorded year, every term with its subject marks, for the
     * printed record of results. Parents see only verified terms.
     *
     * @return array<string, mixed>
     */
    public function recordFor(AcademicSession $year, \App\Models\Student $student, bool $publishedOnly = true): array
    {
        $enrollments = StudentEnrollment::with(['term', 'classSection.form'])
            ->where('academic_session_id', $year->id)->where('student_id', $student->id)
            ->get()->sortBy(fn ($e) => $e->term?->term_number)->values();
        abort_if($enrollments->isEmpty(), 404);

        $results = TermResult::with(['subjectResults.subject', 'term'])
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->when($publishedOnly, fn ($q) => $q->where('is_published', true))
            ->get()->sortBy(fn ($r) => $r->term?->term_number)->values();
        $last = $enrollments->last();
        $sheets = RecordedSheet::with('verifiedBy')->where('academic_session_id', $year->id)
            ->where('class_section_id', $last->class_section_id)->where('status', 'verified')->get();

        $subjects = $results->flatMap(fn ($r) => $r->subjectResults)->map(fn ($sr) => $sr->subject)->filter()->unique('id')->sortBy('name')->values();

        return [
            'year' => $year,
            'student' => $student,
            'section' => $last->classSection,
            'enrollment' => $last,
            'results' => $results,
            'subjects' => $subjects,
            'verifiedBy' => $sheets->map(fn ($s) => $s->verifiedBy?->full_name)->filter()->unique()->implode(', '),
            'school' => \App\Models\SchoolSetting::current(),
            // The year's figures only once every term is visible.
            'showAnnual' => ! $publishedOnly || $results->count() === Term::count(),
        ];
    }

    /**
     * Places by value, ties sharing a place and the next skipping (1, 2, 2, 4),
     * as on the report cards computed here.
     *
     * @param  Collection<int|string, ?float>  $values
     * @return array<int|string, int>
     */
    private function rank(Collection $values): array
    {
        $sorted = $values->filter(fn ($v) => $v !== null)->sortDesc();
        $ranks = [];
        $position = 0;
        $previous = null;
        $place = 0;
        foreach ($sorted as $key => $value) {
            $position++;
            if ($previous === null || abs($value - $previous) >= 0.005) {
                $place = $position;
            }
            $ranks[$key] = $place;
            $previous = $value;
        }

        return $ranks;
    }

    private function number(string $raw): ?float
    {
        $raw = str_replace([' ', "\u{00A0}"], '', trim($raw));
        if ($raw === '' || ! preg_match('/^\d+([.,]\d+)?$/', $raw)) {
            return null;
        }

        return (float) str_replace(',', '.', $raw);
    }

    private function key(?string $text): string
    {
        return rtrim(str_replace([' ', '-', '.'], '_', mb_strtolower(trim((string) $text))), '*_');
    }
}
