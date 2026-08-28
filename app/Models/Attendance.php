<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'student_enrollment_id', 'date', 'status', 'check_in_time', 'reason', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function enrollment() { return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id'); }
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); }
}
