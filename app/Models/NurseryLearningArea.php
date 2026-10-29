<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * A domain of a nursery child's development — language, pre-mathematics,
 * psychomotor skills — under which competences are grouped on the report.
 */
class NurseryLearningArea extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'name', 'display_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function competences()
    {
        return $this->hasMany(NurseryCompetence::class)->orderBy('display_order')->orderBy('id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('id');
    }
}
