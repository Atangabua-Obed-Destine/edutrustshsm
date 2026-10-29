<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One line of the Income & Expenditure sheet — e.g. "401 Travelling".
 *
 * A budget line is a management reporting dimension, not a ledger account.
 * The statutory chart is organised by nature of expense; the sheet is
 * organised by activity and is finer grained. The two are joined through
 * default_account_mappings, where a category resolves to both.
 */
class BudgetLine extends Model
{
    use Auditable, BelongsToBranch;

    public const SECTION_INCOME = 'income';
    public const SECTION_EXPENDITURE = 'expenditure';
    public const SECTION_CAPITAL = 'capital';

    public const SECTIONS = [self::SECTION_INCOME, self::SECTION_EXPENDITURE, self::SECTION_CAPITAL];

    protected $fillable = [
        'branch_id', 'code', 'name', 'name_fr', 'section', 'is_header', 'parent_id',
        'school_level', 'profit_centre', 'is_payroll', 'is_standard', 'sort_order', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_header' => 'boolean',
            'is_payroll' => 'boolean',
            'is_standard' => 'boolean',
            'status' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return array<string, string> */
    public static function sectionLabels(): array
    {
        return [
            self::SECTION_INCOME => __('Income'),
            self::SECTION_EXPENDITURE => __('Expenditure'),
            self::SECTION_CAPITAL => __('Capital'),
        ];
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('code');
    }

    public function mappings()
    {
        return $this->hasMany(DefaultAccountMapping::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeSection($query, string $section)
    {
        return $query->where('section', $section);
    }

    /** Only lines that can carry a figure — headers total their children. */
    public function scopePostable($query)
    {
        return $query->where('is_header', false);
    }

    public function getLabelAttribute(): string
    {
        return $this->code.' — '.$this->displayName();
    }

    /** The name in the admin's language, falling back to the English one. */
    public function displayName(): string
    {
        return app()->getLocale() === 'fr' && $this->name_fr ? $this->name_fr : $this->name;
    }

    /** The whole sheet in print order, headings followed by their lines. */
    public static function sheet(bool $activeOnly = true): Collection
    {
        $lines = static::query()
            ->when($activeOnly, fn ($q) => $q->where('status', true))
            ->orderBy('sort_order')->orderBy('code')
            ->get();

        return static::inTreeOrder($lines);
    }

    /**
     * Flatten lines so each heading is followed immediately by the lines filed
     * under it. The heading structure decides the order and the numbers
     * order within it. A line whose heading is retired or missing is kept at
     * the top level of its section: dropping it would quietly remove money.
     *
     * @param  Collection<int, self>  $lines
     * @return Collection<int, self>
     */
    public static function inTreeOrder(Collection $lines): Collection
    {
        $ids = $lines->pluck('id')->all();
        $childrenOf = $lines
            ->filter(fn (self $line) => $line->parent_id && in_array($line->parent_id, $ids, true))
            ->sortBy([['sort_order', 'asc'], ['code', 'asc']])
            ->groupBy('parent_id');

        $ordered = collect();

        foreach (self::SECTIONS as $section) {
            $topLevel = $lines
                ->where('section', $section)
                ->filter(fn (self $line) => ! $line->parent_id || ! in_array($line->parent_id, $ids, true))
                ->sortBy([['sort_order', 'asc'], ['code', 'asc']]);

            foreach ($topLevel as $line) {
                $ordered->push($line);
                foreach ($childrenOf->get($line->id, collect()) as $child) {
                    $ordered->push($child);
                }
            }
        }

        return $ordered->values();
    }

    /**
     * Where each heading's group ends, as [last child line id => heading], so
     * the screen, the PDF and the CSV draw subtotals in the same place.
     *
     * @param  Collection<int, self>  $orderedLines
     * @return array<int, self>
     */
    public static function groupEnds(Collection $orderedLines): array
    {
        $ends = [];
        $currentHeader = null;
        $lastChildId = null;

        foreach ($orderedLines as $line) {
            if ($line->is_header) {
                if ($currentHeader && $lastChildId) {
                    $ends[$lastChildId] = $currentHeader;
                }
                $currentHeader = $line;
                $lastChildId = null;

                continue;
            }

            if ($currentHeader && $line->parent_id === $currentHeader->id) {
                $lastChildId = $line->id;

                continue;
            }

            if ($currentHeader && $lastChildId) {
                $ends[$lastChildId] = $currentHeader;
            }
            $currentHeader = null;
            $lastChildId = null;
        }

        if ($currentHeader && $lastChildId) {
            $ends[$lastChildId] = $currentHeader;
        }

        return $ends;
    }

    /**
     * Give every heading the sum of the lines filed under it.
     *
     * @param  Collection<int, self>  $sheetLines
     * @param  array<int, float>  $figures  keyed by line id
     * @return array<int, float>
     */
    public static function rollUp(Collection $sheetLines, array $figures): array
    {
        foreach ($sheetLines as $line) {
            if (! $line->is_header) {
                continue;
            }
            $figures[$line->id] = $sheetLines
                ->where('parent_id', $line->id)
                ->sum(fn ($child) => (float) ($figures[$child->id] ?? 0));
        }

        return $figures;
    }

    /**
     * A section's total. Headers are excluded so their children are not
     * counted a second time.
     *
     * @param  array<int, float>  $figures
     */
    public static function sectionTotal(Collection $sheetLines, array $figures, string $section): float
    {
        return $sheetLines
            ->where('section', $section)
            ->where('is_header', false)
            ->sum(fn ($line) => (float) ($figures[$line->id] ?? 0));
    }
}
