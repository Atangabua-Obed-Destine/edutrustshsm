<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\IsAddressed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A notice the school posts — to parents, to staff, or to both.
 *
 * This was pta_announcements. The PTA is now one category on it rather than the
 * only voice the school had.
 *
 * A notice with no published_at is a draft and reaches nobody; expires_at takes
 * it off the board without deleting it, because "what did the school say in
 * March" is a question that gets asked.
 */
class Announcement extends Model
{
    use Auditable, BelongsToBranch, IsAddressed;

    protected $fillable = [
        'branch_id', 'category_id', 'reference', 'title', 'body', 'attachment',
        'scope', 'audience', 'target_form_id', 'target_class_section_id',
        'published_at', 'expires_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function category()
    {
        return $this->belongsTo(AnnouncementCategory::class, 'category_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Live on the board right now: published, and not yet expired. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    public function scopeInCategory(Builder $query, string $slug): Builder
    {
        return $query->whereHas('category', fn (Builder $q) => $q->where('slug', $slug));
    }

    public function isPublished(): bool
    {
        return $this->published_at && $this->published_at->isPast();
    }

    public function hasExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function status(): string
    {
        return match (true) {
            ! $this->published_at => __('Draft'),
            $this->published_at->isFuture() => __('Scheduled'),
            $this->hasExpired() => __('Expired'),
            default => __('Published'),
        };
    }

    /**
     * The next reference in this branch's run — NOT-2026-0001.
     *
     * Read without the branch scope so two branches cannot be handed the same
     * number by a query that happens to be running in All-Branches mode.
     */
    public static function nextReference(?int $branchId): string
    {
        $prefix = 'NOT-'.now()->year.'-';

        $last = static::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
