<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * What a notice is about — PTA, Academic, Finance and so on.
 *
 * The PTA used to be the only channel the school had. It is now one category
 * among several, which is what lets a head teacher post without pretending to
 * be the PTA.
 */
class AnnouncementCategory extends Model
{
    use BelongsToBranch;

    public const PTA = 'pta';

    protected $fillable = ['branch_id', 'name', 'slug', 'colour', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class, 'category_id');
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** The PTA category for the branch in context, used to keep PTA screens on their own notices. */
    public static function pta(): ?self
    {
        return static::where('slug', self::PTA)->first();
    }

    /** Tailwind classes for the category badge. */
    public function badgeClasses(): string
    {
        return match ($this->colour) {
            'emerald' => 'bg-emerald-100 text-emerald-700',
            'blue' => 'bg-blue-100 text-blue-700',
            'amber' => 'bg-amber-100 text-amber-700',
            'rose' => 'bg-rose-100 text-rose-700',
            'violet' => 'bg-violet-100 text-violet-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }
}
