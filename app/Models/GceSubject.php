<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * A subject as the GCE Board lists it. Board codes are what the entry file is
 * read by, and they do not match internal subject records, so this is its own
 * catalogue that an internal Subject may optionally map onto.
 */
class GceSubject extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'code', 'name', 'level', 'subject_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function subject() { return $this->belongsTo(Subject::class); }

    public function candidates()
    {
        return $this->belongsToMany(GceCandidate::class, 'gce_candidate_subjects', 'gce_subject_id', 'gce_candidate_id');
    }

    public function scopeForLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getLabelAttribute(): string
    {
        return $this->code.' — '.$this->name;
    }
}
