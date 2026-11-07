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
        'destination_school', 'exit_recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
            'withdrawal_date' => 'date',
        ];
    }

    /**
     * The next matricule for a batch: school code + batch shortcode + a
     * four-digit number (e.g. 5SNPS260012). One rule for the registration
     * form and the bulk import, so a school's numbers all look alike.
     * Numbers are read, not sorted as text, so a longer imported number
     * cannot throw the sequence off.
     */
    public static function nextMatricule(Batch $batch): string
    {
        $prefix = (SchoolSetting::current()?->school_code ?? 'SCH').$batch->shortcode;

        $last = static::withoutGlobalScopes()->where('student_id', 'like', $prefix.'%')->pluck('student_id')
            ->map(fn ($id) => substr($id, strlen($prefix)))
            ->filter(fn ($n) => ctype_digit($n))
            ->map(fn ($n) => (int) $n)
            ->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
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
    /** The primary guardian — the one the school writes to first. */
    public function guardian() { return $this->belongsTo(Guardian::class); }

    /** Everyone linked to this child, primary or not. */
    public function guardians()
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
            ->withPivot(['relationship', 'is_primary', 'can_access_portal'])
            ->withTimestamps();
    }

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
