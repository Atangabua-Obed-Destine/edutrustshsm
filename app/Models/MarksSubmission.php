<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class MarksSubmission extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'class_section_id', 'academic_session_id', 'subject_id', 'sequence_id', 'teacher_id', 'status',
        'total_students', 'marks_entered', 'class_average', 'highest_mark',
        'lowest_mark', 'pass_rate', 'submitted_at', 'approved_by', 'approved_at',
        'admin_comment',
    ];

    protected function casts(): array
    {
        return [
            'class_average' => 'decimal:2',
            'highest_mark' => 'decimal:1',
            'lowest_mark' => 'decimal:1',
            'pass_rate' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * A submission belongs to a school year: P3A Maths Sequence 1 happens
     * every year. One created without a year takes the current one.
     */
    protected static function booted(): void
    {
        static::creating(function (self $submission) {
            $submission->academic_session_id ??= AcademicSession::current()?->id;
        });
    }

    /** This year's submissions, or a given year's. */
    public function scopeForSession($query, ?int $sessionId = null)
    {
        return $query->where('marks_submissions.academic_session_id', $sessionId ?? AcademicSession::current()?->id);
    }

    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
    public function classSection() { return $this->belongsTo(ClassSection::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function sequence() { return $this->belongsTo(Sequence::class); }
    public function teacher() { return $this->belongsTo(User::class, 'teacher_id'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
}
