<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class GceCandidate extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id', 'gce_registration_session_id', 'student_id', 'student_enrollment_id',
        'candidate_number', 'fee_amount', 'amount_paid', 'status', 'notes', 'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'fee_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function registrationSession() { return $this->belongsTo(GceRegistrationSession::class, 'gce_registration_session_id'); }
    public function student() { return $this->belongsTo(Student::class); }
    public function enrollment() { return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id'); }
    public function registeredBy() { return $this->belongsTo(User::class, 'registered_by'); }

    public function subjects()
    {
        return $this->belongsToMany(GceSubject::class, 'gce_candidate_subjects', 'gce_candidate_id', 'gce_subject_id')
            ->orderBy('code');
    }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->fee_amount - (float) $this->amount_paid, 2);
    }

    public function isPaid(): bool
    {
        return $this->balance <= 0.005;
    }
}
