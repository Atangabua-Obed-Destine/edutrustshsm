<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Mark extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'student_enrollment_id', 'subject_id', 'sequence_id', 'score', 'grade',
        'is_absent', 'entered_by', 'status', 'approved_by', 'submitted_at',
        'approved_at', 'admin_comment',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:1',
            'is_absent' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function enrollment() { return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id'); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function sequence() { return $this->belongsTo(Sequence::class); }
    public function enteredBy() { return $this->belongsTo(User::class, 'entered_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
}
