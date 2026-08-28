<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'student_id', 'batch_id', 'user_id', 'first_name', 'last_name', 'other_names',
        'date_of_birth', 'gender', 'nationality', 'place_of_birth', 'region_of_origin', 'religion',
        'blood_group', 'photo', 'email', 'phone', 'home_address', 'town', 'guardian_id',
        'allergies', 'medical_conditions', 'special_needs',
        'birth_certificate', 'primary_certificate', 'gce_ol_certificate',
        'previous_school', 'previous_class', 'transfer_certificate', 'medical_certificate',
        'status', 'admission_date', 'withdrawal_date', 'withdrawal_reason',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
            'withdrawal_date' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([$this->first_name, $this->other_names, $this->last_name]);
        return implode(' ', $parts);
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    public function user() { return $this->belongsTo(User::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function guardian() { return $this->belongsTo(Guardian::class); }

    public function enrollments()
    {
        return $this->hasMany(StudentEnrollment::class)->orderByDesc('academic_session_id');
    }

    public function currentEnrollment()
    {
        return $this->hasOne(StudentEnrollment::class)
            ->whereHas('academicSession', fn ($q) => $q->where('is_current', true))
            ->where('status', 'active');
    }
}
