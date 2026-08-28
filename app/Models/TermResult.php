<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class TermResult extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'student_enrollment_id', 'term_id', 'total_weighted_score', 'total_coefficient',
        'term_average', 'overall_grade', 'class_rank', 'total_students', 'class_average',
        'highest_average', 'lowest_average', 'days_present', 'days_absent',
        'total_school_days', 'class_teacher_remark', 'principal_remark', 'conduct',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'total_weighted_score' => 'decimal:2',
            'total_coefficient' => 'decimal:1',
            'term_average' => 'decimal:2',
            'class_average' => 'decimal:2',
            'highest_average' => 'decimal:2',
            'lowest_average' => 'decimal:2',
            'is_published' => 'boolean',
        ];
    }

    public function enrollment() { return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id'); }
    public function term() { return $this->belongsTo(Term::class); }
    public function subjectResults() { return $this->hasMany(SubjectTermResult::class); }
}
