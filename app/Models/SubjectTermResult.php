<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class SubjectTermResult extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'term_result_id', 'subject_id', 'coefficient', 'sequence_1_score',
        'sequence_2_score', 'sequence_3_score', 'sequence_scores', 'resolved_weights',
        'term_average', 'weighted_score',
        'grade', 'subject_rank', 'subject_total_students', 'teacher_name',
    ];

    protected function casts(): array
    {
        return [
            'sequence_scores' => 'array',
            'resolved_weights' => 'array',
            'coefficient' => 'decimal:1',
            'sequence_1_score' => 'decimal:1',
            'sequence_2_score' => 'decimal:1',
            'sequence_3_score' => 'decimal:1',
            'term_average' => 'decimal:2',
            'weighted_score' => 'decimal:2',
        ];
    }

    public function termResult() { return $this->belongsTo(TermResult::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
}
