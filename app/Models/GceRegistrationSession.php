<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * One GCE exam series: "June 2026, Ordinary Level".
 *
 * Subject-count rules and fees live here rather than in code, because the Board
 * changes them between series and a school should not need a deployment to
 * follow this year's rules.
 */
class GceRegistrationSession extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id', 'academic_session_id', 'name', 'level', 'exam_year',
        'centre_number', 'opens_on', 'closes_on', 'min_subjects', 'max_subjects',
        'fee_per_subject', 'base_fee', 'status',
    ];

    protected function casts(): array
    {
        return [
            'opens_on' => 'date',
            'closes_on' => 'date',
            'fee_per_subject' => 'decimal:2',
            'base_fee' => 'decimal:2',
        ];
    }

    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
    public function candidates() { return $this->hasMany(GceCandidate::class); }

    public function forms()
    {
        return $this->belongsToMany(Form::class, 'gce_session_form', 'gce_registration_session_id', 'form_id');
    }

    /** Whether entries may still be added or changed. */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function getLevelLabelAttribute(): string
    {
        return $this->level === 'a_level' ? __('Advanced Level') : __('Ordinary Level');
    }

    /** What a candidate owes for a given number of subjects. */
    public function feeFor(int $subjectCount): float
    {
        return round((float) $this->base_fee + ((float) $this->fee_per_subject * $subjectCount), 2);
    }
}
