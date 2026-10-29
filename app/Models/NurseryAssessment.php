<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * One rating: this pupil, this term, this competence — acquired, in progress,
 * or not yet. There is no number behind it and no rank built on it.
 */
class NurseryAssessment extends Model
{
    use Auditable, BelongsToBranch;

    public const ACQUIRED = 'acquired';
    public const DEVELOPING = 'developing';
    public const NOT_YET = 'not_yet';

    public const RATINGS = [self::ACQUIRED, self::DEVELOPING, self::NOT_YET];

    protected $fillable = [
        'branch_id', 'student_enrollment_id', 'term_id', 'nursery_competence_id', 'rating', 'assessed_by',
    ];

    /** @return array<string, string> rating => label */
    public static function labels(): array
    {
        return [
            self::ACQUIRED => __('Acquired'),
            self::DEVELOPING => __('In progress'),
            self::NOT_YET => __('Not yet'),
        ];
    }

    /** @return array<string, string> rating => short code printed on the card */
    public static function codes(): array
    {
        return [
            self::ACQUIRED => __('A'),
            self::DEVELOPING => __('IP'),
            self::NOT_YET => __('NY'),
        ];
    }

    public function enrollment()
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function competence()
    {
        return $this->belongsTo(NurseryCompetence::class, 'nursery_competence_id');
    }

    public function assessor()
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
