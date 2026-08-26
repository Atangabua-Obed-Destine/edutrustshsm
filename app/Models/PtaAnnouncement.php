<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class PtaAnnouncement extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'title', 'body', 'audience',
        'target_form_id', 'target_class_section_id',
        'published_at', 'expires_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function targetForm() { return $this->belongsTo(Form::class, 'target_form_id'); }
    public function targetClass() { return $this->belongsTo(ClassSection::class, 'target_class_section_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    public function isPublished(): bool
    {
        return $this->published_at && $this->published_at->isPast();
    }
}
