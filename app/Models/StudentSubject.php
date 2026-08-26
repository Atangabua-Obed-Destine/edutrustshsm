<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class StudentSubject extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'student_enrollment_id', 'subject_id', 'coefficient',
    ];

    protected function casts(): array
    {
        return ['coefficient' => 'decimal:1'];
    }

    public function enrollment()
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
