<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * One thing a nursery child is expected to be able to do by the end of the
 * year: "counts objects up to ten", "holds a pencil correctly".
 */
class NurseryCompetence extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'nursery_learning_area_id', 'form_id', 'description', 'display_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function learningArea()
    {
        return $this->belongsTo(NurseryLearningArea::class, 'nursery_learning_area_id');
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function assessments()
    {
        return $this->hasMany(NurseryAssessment::class);
    }

    /** Competences that apply to a given nursery class: its own plus the shared ones. */
    public function scopeForForm($query, int $formId)
    {
        return $query->where(fn ($q) => $q->whereNull('form_id')->orWhere('form_id', $formId));
    }
}
