<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One state change on a mark submission.
 *
 * marks_submissions keeps only the latest approved_by/approved_at, so without
 * this the history of who submitted, who returned it and why was lost.
 */
class MarksWorkflowLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'marks_submission_id', 'from_status', 'to_status', 'user_id', 'note', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function submission()
    {
        return $this->belongsTo(MarksSubmission::class, 'marks_submission_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
