<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class StudentEnrollment extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'student_id', 'academic_session_id', 'term_id', 'class_section_id',
        'stream_id', 'residence_type', 'enrollment_date', 'status',
        'final_average', 'final_rank',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_date' => 'date',
            'final_average' => 'decimal:2',
        ];
    }

    public function student() { return $this->belongsTo(Student::class); }
    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function classSection() { return $this->belongsTo(ClassSection::class); }
    public function stream() { return $this->belongsTo(Stream::class); }
    public function studentSubjects() { return $this->hasMany(StudentSubject::class); }
    public function marks() { return $this->hasMany(Mark::class); }
    public function termResults() { return $this->hasMany(TermResult::class); }
    public function attendances() { return $this->hasMany(Attendance::class); }
    public function fees() { return $this->hasMany(StudentFee::class); }

    /**
     * Ensure all core subjects from the curriculum are registered in student_subjects.
     */
    public function syncCoreSubjects(): void
    {
        $formId = $this->classSection->form_id ?? ClassSection::find($this->class_section_id)?->form_id;
        $streamId = $this->stream_id;

        if (!$formId) return;

        $coreSubjects = \Illuminate\Support\Facades\DB::table('form_subject')
            ->where('form_id', $formId)
            ->where('stream_id', $streamId)
            ->where('type', 'core')
            ->get(['subject_id', 'coefficient']);

        $existing = StudentSubject::where('student_enrollment_id', $this->id)
            ->pluck('subject_id')
            ->toArray();

        $toInsert = [];
        $now = now();
        foreach ($coreSubjects as $cs) {
            if (!in_array($cs->subject_id, $existing)) {
                $toInsert[] = [
                    'branch_id' => $this->branch_id,
                    'student_enrollment_id' => $this->id,
                    'subject_id' => $cs->subject_id,
                    'coefficient' => $cs->coefficient,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($toInsert)) {
            \Illuminate\Support\Facades\DB::table('student_subjects')->insert($toInsert);
        }
    }

    public static function syncCoreSubjectsForClass(int $classSectionId, int $sessionId): void
    {
        $enrollments = static::with('classSection')
            ->where('class_section_id', $classSectionId)
            ->where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->get();

        foreach ($enrollments as $enrollment) {
            $enrollment->syncCoreSubjects();
        }
    }

    /**
     * Auto-assign fees from fee structures for this enrollment.
     *
     * @return array{assigned: int, skipped: int, categories: array}
     */
    public function syncFees(): array
    {
        $sessionId = $this->academic_session_id;
        $formId = $this->classSection->form_id ?? ClassSection::find($this->class_section_id)?->form_id;
        $streamId = $this->stream_id;

        $result = ['assigned' => 0, 'skipped' => 0, 'categories' => []];

        if (!$sessionId || !$formId) {
            return $result;
        }

        $structures = FeeStructure::where('academic_session_id', $sessionId)
            ->where('form_id', $formId)
            ->where(function ($q) use ($streamId) {
                $q->where('stream_id', $streamId)
                  ->orWhereNull('stream_id');
            })
            ->whereHas('feeCategory', fn ($q) => $q->where('is_active', true))
            ->with('feeCategory')
            ->orderByRaw('stream_id IS NULL ASC')
            ->get();

        $structuresByCategory = [];
        foreach ($structures as $structure) {
            $catId = $structure->fee_category_id;
            if (!isset($structuresByCategory[$catId])) {
                $structuresByCategory[$catId] = $structure;
            }
        }

        $existingCategoryIds = StudentFee::where('student_enrollment_id', $this->id)
            ->pluck('fee_category_id')
            ->toArray();

        foreach ($structuresByCategory as $categoryId => $structure) {
            if ($structure->feeCategory->is_boarding && $this->residence_type === 'day') {
                $result['skipped']++;
                continue;
            }

            if (in_array($categoryId, $existingCategoryIds)) {
                $result['skipped']++;
                continue;
            }

            $amount = $structure->amount;

            StudentFee::create([
                'branch_id'             => $this->branch_id,
                'student_enrollment_id' => $this->id,
                'fee_category_id'       => $categoryId,
                'original_amount'       => $amount,
                'discount_amount'       => 0,
                'waiver_amount'         => 0,
                'net_amount'            => $amount,
                'paid_amount'           => 0,
                'balance'               => $amount,
                'status'                => 'unpaid',
                'notes'                 => 'Auto-assigned from fee structure',
            ]);

            $result['assigned']++;
            $result['categories'][] = $structure->feeCategory->name;
        }

        return $result;
    }

    public static function syncFeesForClass(int $classSectionId, int $sessionId): array
    {
        $enrollments = static::with('classSection')
            ->where('class_section_id', $classSectionId)
            ->where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->get();

        $totalAssigned = 0;
        $totalSkipped = 0;

        foreach ($enrollments as $enrollment) {
            $r = $enrollment->syncFees();
            $totalAssigned += $r['assigned'];
            $totalSkipped += $r['skipped'];
        }

        return ['assigned' => $totalAssigned, 'skipped' => $totalSkipped];
    }
}
