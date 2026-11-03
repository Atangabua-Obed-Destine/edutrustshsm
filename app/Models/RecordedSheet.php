<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * One class's results for one term of a year recorded from before the system.
 *
 * The unit of the two-person check: someone enters the marks from the paper
 * mark sheet, the head teacher compares them with it and verifies. Verified
 * sheets are locked and their results reach the pupil's history and the
 * parent portal.
 */
class RecordedSheet extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id', 'academic_session_id', 'class_section_id', 'term_id',
        'status', 'out_of', 'evidence_path',
        'entered_by', 'entered_at', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'out_of' => 'decimal:1',
            'entered_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function classSection()
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
