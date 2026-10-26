<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\IsAddressed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A dated entry on the school calendar — sports day, a PTA meeting, a holiday.
 *
 * The reference system keeps events global. Here they carry the same reach as
 * a notice, because a nursery sports day is not news to a secondary parent.
 *
 * Times are optional: most school events are all-day, and a blank time should
 * read as "all day" rather than as midnight.
 */
class Event extends Model
{
    use Auditable, BelongsToBranch, IsAddressed;

    protected $fillable = [
        'branch_id', 'category_id', 'title', 'description',
        'starts_on', 'ends_on', 'starts_at', 'ends_at', 'venue',
        'scope', 'audience', 'target_form_id', 'target_class_section_id',
        'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'published_at' => 'datetime',
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

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * Still to come, or running now.
     *
     * Compared on the end date, not the start: a week-long event is still worth
     * showing on its third day.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('ends_on', '>=', now()->toDateString());
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->whereDate('ends_on', '<', now()->toDateString());
    }

    public function isAllDay(): bool
    {
        return ! $this->starts_at;
    }

    public function isPublished(): bool
    {
        return $this->published_at && $this->published_at->isPast();
    }

    public function isRunning(): bool
    {
        return $this->starts_on->isPast() && $this->ends_on->endOfDay()->isFuture();
    }

    /** One line for the date, collapsing a single-day event to one date. */
    public function whenLabel(): string
    {
        $start = $this->starts_on->format('j M Y');

        return $this->starts_on->isSameDay($this->ends_on)
            ? $start
            : $start.' – '.$this->ends_on->format('j M Y');
    }
}
