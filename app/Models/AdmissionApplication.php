<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class AdmissionApplication extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'applicant_id', 'application_number', 'academic_session_id', 'form_id', 'stream_id',
        'first_name', 'last_name', 'other_names', 'date_of_birth', 'gender', 'blood_group',
        'nationality', 'place_of_birth', 'region_of_origin', 'religion', 'phone', 'email',
        'home_address', 'town', 'previous_school', 'previous_class',
        'father_name', 'father_phone', 'father_email', 'father_occupation', 'father_address',
        'mother_name', 'mother_phone', 'mother_email', 'mother_occupation', 'mother_address',
        'guardian_name', 'guardian_relationship', 'guardian_phone', 'guardian_email',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
        'photo', 'birth_certificate', 'primary_certificate', 'gce_ol_certificate',
        'transfer_certificate', 'medical_certificate',
        'status', 'admin_notes', 'reviewed_by', 'reviewed_at', 'student_id',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([$this->first_name, $this->other_names, $this->last_name]);
        return implode(' ', $parts);
    }

    /**
     * Generate a unique application number: APP-{YEAR}-{5-digit sequence}.
     * The sequence continues from the highest existing number for the current
     * year across ALL branches (numbers must be globally unique — the column is
     * unique). Loops defensively in the rare event of a race/collision.
     */
    public static function generateApplicationNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "APP-{$year}-";

        $last = static::withoutBranchScope()
            ->where('application_number', 'like', $prefix . '%')
            ->orderByDesc('application_number')
            ->value('application_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        // Guard against collisions (e.g. concurrent submissions).
        do {
            $number = $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $exists = static::withoutBranchScope()->where('application_number', $number)->exists();
            $next++;
        } while ($exists);

        return $number;
    }

    /**
     * Colour scheme + label for the application status, consumed by the
     * admissions views (`bg`, `color`, `label`). Falls back gracefully for any
     * unexpected status so the views never receive null.
     *
     * @return array{bg: string, color: string, label: string}
     */
    public function getStatusBadgeAttribute(): array
    {
        $map = [
            'pending'      => ['bg' => '#fef9c3', 'color' => '#ca8a04', 'label' => __('Pending')],
            'under_review' => ['bg' => '#dbeafe', 'color' => '#2563eb', 'label' => __('Under Review')],
            'accepted'     => ['bg' => '#dcfce7', 'color' => '#16a34a', 'label' => __('Accepted')],
            'rejected'     => ['bg' => '#fee2e2', 'color' => '#dc2626', 'label' => __('Rejected')],
            'enrolled'     => ['bg' => '#f0fdfa', 'color' => '#0d9488', 'label' => __('Enrolled')],
        ];

        return $map[$this->status] ?? [
            'bg'    => '#f1f5f9',
            'color' => '#475569',
            'label' => $this->status ? ucfirst(str_replace('_', ' ', $this->status)) : __('Unknown'),
        ];
    }

    public function applicant() { return $this->belongsTo(Applicant::class); }
    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
    public function form() { return $this->belongsTo(Form::class); }
    public function stream() { return $this->belongsTo(Stream::class); }
    public function reviewedBy() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function student() { return $this->belongsTo(Student::class); }
}
