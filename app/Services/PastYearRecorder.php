<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\TermResult;
use App\Support\SchoolCsv;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A school year that happened before the school used this system.
 *
 * The pupils are the ones already registered. Each gets enrolment rows for
 * that year in the class they were in, one per term as for a live year, so
 * every screen that reads a pupil's record (history, the repeater mark on a
 * class list, last year's arrears) finds the year without knowing it was
 * recorded. What keeps it from disturbing the present:
 *
 *  - the rows are never "active": no fees are billed, no register opens, and
 *    the year can never be made current;
 *  - results are recorded from the paper, never computed (RecordedResults).
 */
class PastYearRecorder
{
    public function createYear(string $name, string $start, string $end): AcademicSession
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        if ($end->lte($start)) {
            throw new RuntimeException(__('The year must end after it starts.'));
        }

        $current = AcademicSession::current();
        if ($current && $end->gte($current->start_date)) {
            throw new RuntimeException(__('A past year must end before the current year begins (:date). If the current year\'s dates are not the real ones yet, correct them first under Academic Years.', [
                'date' => $current->start_date->format('d/m/Y'),
            ]));
        }

        $overlap = AcademicSession::where('start_date', '<=', $end)->where('end_date', '>=', $start)->first();
        if ($overlap) {
            throw new RuntimeException(__('These dates overlap the year :name.', ['name' => $overlap->name]));
        }

        if (AcademicSession::where('name', $name)->exists()) {
            throw new RuntimeException(__('A year named :name already exists.', ['name' => $name]));
        }

        return AcademicSession::create([
            'name' => $name, 'start_date' => $start, 'end_date' => $end,
            'status' => 'past', 'is_current' => false, 'is_recorded' => true,
        ]);
    }

    /** @return Collection<int, Term> the terms of every year, in order */
    public function terms(): Collection
    {
        return Term::orderBy('term_number')->get();
    }

    /**
     * Where a pupil now in $current most likely was last year: the class
     * before it, same stream letter if there is one.
     */
    public function likelyClassLastYear(ClassSection $current): ?ClassSection
    {
        $form = $current->form;
        $previous = Form::where('school_level', $form->school_level)
            ->where('is_active', true)
            ->where('display_order', '<', $form->display_order)
            ->orderByDesc('display_order')
            ->first();

        if (! $previous) {
            return null;
        }

        $sections = ClassSection::where('form_id', $previous->id)->where('is_active', true)->orderBy('name')->get();
        $letter = $current->section;

        return $sections->firstWhere('section', $letter) ?? $sections->first();
    }

    /**
     * This year's pupils of a class, with where each was in the recorded year.
     *
     * @return Collection<int, array{student: Student, residence: string, recorded: ?int, suggested: ?int, locked: bool}>
     */
    public function placements(AcademicSession $year, ClassSection $current): Collection
    {
        $now = AcademicSession::current();
        $suggested = $this->likelyClassLastYear($current)?->id;

        $enrollments = StudentEnrollment::with('student')
            ->where('academic_session_id', $now?->id)
            ->where('class_section_id', $current->id)
            ->where('status', 'active')
            ->get()
            ->unique('student_id')
            ->sortBy(fn ($e) => [$e->student?->last_name, $e->student?->first_name]);

        $recorded = StudentEnrollment::where('academic_session_id', $year->id)
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->get()
            ->groupBy('student_id');

        $withResults = TermResult::whereIn('student_enrollment_id', $recorded->flatten()->pluck('id'))
            ->with('enrollment:id,student_id')
            ->get()
            ->pluck('enrollment.student_id')
            ->flip();

        return $enrollments->map(fn ($e) => [
            'student' => $e->student,
            'residence' => $e->residence_type ?: 'day',
            'recorded' => $recorded->get($e->student_id)?->first()?->class_section_id,
            'suggested' => $suggested,
            'locked' => $withResults->has($e->student_id),
        ])->values();
    }

    /**
     * Put a pupil in a class for the recorded year, or take them out of it.
     *
     * The decision follows from the class: the same class level as now means
     * the pupil repeated, a lower one that they were promoted.
     */
    public function place(AcademicSession $year, Student $student, ?int $sectionId, string $residence = 'day'): void
    {
        $this->assertRecorded($year);

        $existing = StudentEnrollment::where('academic_session_id', $year->id)->where('student_id', $student->id)->get();
        $hasResults = TermResult::whereIn('student_enrollment_id', $existing->pluck('id'))->exists();
        $hasFees = StudentFee::whereIn('student_enrollment_id', $existing->pluck('id'))->exists();

        $unchanged = $existing->isNotEmpty() && $existing->every(fn ($e) => (int) $e->class_section_id === (int) $sectionId);
        if ($unchanged) {
            return;
        }

        if ($existing->isNotEmpty() && ($hasResults || $hasFees)) {
            throw new RuntimeException(__(':name already has results or fees recorded in :class for :year. Remove them before moving the pupil.', [
                'name' => $student->full_name,
                'class' => $existing->first()->classSection?->name,
                'year' => $year->name,
            ]));
        }

        DB::transaction(function () use ($year, $student, $sectionId, $residence, $existing) {
            StudentEnrollment::whereIn('id', $existing->pluck('id'))->delete();

            if (! $sectionId) {
                return;
            }

            $section = ClassSection::with('form')->findOrFail($sectionId);
            $nowForm = $student->currentEnrollment?->classSection?->form_id;
            $decision = $nowForm && (int) $nowForm === (int) $section->form_id ? 'repeated' : 'promoted';

            $terms = $this->terms();
            foreach ($terms as $i => $term) {
                // Never "active": the enrolment observer bills only active
                // rows, and every live screen reads only those.
                StudentEnrollment::create([
                    'branch_id' => $student->branch_id,
                    'student_id' => $student->id,
                    'academic_session_id' => $year->id,
                    'term_id' => $term->id,
                    'class_section_id' => $section->id,
                    'residence_type' => in_array($residence, ['day', 'boarding', 'half_boarding'], true) ? $residence : 'day',
                    'enrollment_date' => $year->start_date,
                    'status' => $i === $terms->count() - 1 ? $decision : 'completed',
                ]);
            }
        });
    }

    /**
     * Last year's unpaid fees, from the old books.
     *
     * Each line becomes a fee on the pupil's recorded year, owing only what
     * was still unpaid at the switch. The arrears rules then collect it,
     * oldest first, at the same counter.
     *
     * @return array{saved: int, errors: list<string>}
     */
    public function importArrears(AcademicSession $year, string $path): array
    {
        $this->assertRecorded($year);

        [$headers, $rows] = SchoolCsv::read($path, [
            'student_id' => 'matricule', 'mat' => 'matricule',
            'categorie' => 'category', 'catégorie' => 'category', 'fee' => 'category', 'fee_category' => 'category', 'frais' => 'category',
            'montant' => 'amount', 'owed' => 'amount', 'reste' => 'amount', 'balance' => 'amount', 'solde' => 'amount',
        ]);

        if (! in_array('matricule', $headers, true) || ! in_array('amount', $headers, true)) {
            throw new RuntimeException(__('The file needs at least the columns matricule and amount.'));
        }

        $categories = FeeCategory::all();
        $default = $categories->firstWhere('is_tuition', true) ?? $categories->first();
        $saved = 0;
        $errors = [];

        DB::transaction(function () use ($year, $headers, $rows, $categories, $default, &$saved, &$errors) {
            foreach ($rows as $line => $row) {
                $r = SchoolCsv::combine($headers, $row);
                if (implode('', $r) === '') {
                    continue;
                }

                $student = Student::where('student_id', $r['matricule'])->first();
                $amount = SchoolCsv::amount($r['amount'] ?? '');
                $label = trim($r['category'] ?? '');
                $category = $label === '' ? $default : $categories->first(fn ($c) => mb_strtolower($c->name) === mb_strtolower($label) || mb_strtolower((string) $c->code) === mb_strtolower($label));
                $enrollment = $student ? StudentEnrollment::where('academic_session_id', $year->id)->where('student_id', $student->id)
                    ->with('term')->get()->sortBy(fn ($e) => $e->term?->term_number)->last() : null;

                $problem = match (true) {
                    ! $student => __('no pupil has matricule ":value"', ['value' => $r['matricule']]),
                    ! $enrollment => __(':name has no class recorded for :year: place the pupil first', ['name' => $student->full_name, 'year' => $year->name]),
                    ! $amount || $amount < 1 => __('amount ":value" is not a number of francs', ['value' => $r['amount'] ?? '']),
                    ! $category => __('no fee category is called ":value"', ['value' => $label]),
                    default => null,
                };
                if ($problem) {
                    $errors[] = __('Line :line: :problem', ['line' => $line, 'problem' => $problem]);

                    continue;
                }

                $fee = StudentFee::firstOrNew(['student_enrollment_id' => $enrollment->id, 'fee_category_id' => $category->id]);
                if ($fee->exists && $fee->allocations()->exists()) {
                    $errors[] = __('Line :line (:name): already being paid, left as it is', ['line' => $line, 'name' => $student->full_name]);

                    continue;
                }

                $fee->fill([
                    'branch_id' => $student->branch_id,
                    'original_amount' => $amount, 'discount_amount' => 0, 'waiver_amount' => 0, 'fine_amount' => 0,
                    'paid_amount' => 0,
                    'notes' => __('Unpaid at the switch, from the :year books', ['year' => $year->name]),
                ]);
                $fee->recalculate()->save();
                $saved++;
            }
        });

        if ($saved > 0) {
            AuditLog::log('past_year_arrears_imported', AcademicSession::class, $year->id, null, ['saved' => $saved]);
        }

        return ['saved' => $saved, 'errors' => $errors];
    }

    /** The past-year screens only ever open a recorded year. */
    public function assertRecordedOr404(AcademicSession $year): void
    {
        abort_unless($year->is_recorded, 404);
    }

    public function assertRecorded(AcademicSession $year): void
    {
        if (! $year->is_recorded) {
            throw new RuntimeException(__('Only a year recorded from before the system can be changed here.'));
        }
    }
}
