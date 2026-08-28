<?php

namespace App\Models;

use App\Support\BranchContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-branch key/value settings.
 *
 * Values are read constantly (every page renders at least the receipt and
 * branding keys), so the whole branch's set is loaded once and cached rather
 * than queried per key.
 */
class Setting extends Model
{
    protected $fillable = ['branch_id', 'group', 'key', 'value', 'type', 'is_encrypted'];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }

    /** @var array<string, array<string, mixed>> Per-request cache, keyed by branch. */
    private static array $loaded = [];

    private static function branchId(): ?int
    {
        if (! BranchContext::isActive() || BranchContext::isAllBranches()) {
            return null;
        }

        return BranchContext::current() ?: null;
    }

    private static function cacheKey(?int $branchId): string
    {
        return 'settings.branch.'.($branchId ?? 'global');
    }

    /** @return array<string, mixed> */
    public static function allForBranch(?int $branchId = null): array
    {
        $branchId ??= self::branchId();
        $memo = self::cacheKey($branchId);

        if (isset(self::$loaded[$memo])) {
            return self::$loaded[$memo];
        }

        $rows = cache()->rememberForever($memo, function () use ($branchId) {
            return static::query()
                ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
                // A branch-specific row wins over the global fallback.
                ->orderByRaw('branch_id IS NULL DESC')
                ->get(['key', 'value', 'type', 'is_encrypted'])
                ->mapWithKeys(fn ($r) => [$r->key => self::decode($r)])
                ->all();
        });

        return self::$loaded[$memo] = $rows;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::allForBranch()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general', string $type = 'string', bool $encrypted = false): void
    {
        $branchId = self::branchId();

        static::updateOrCreate(
            ['branch_id' => $branchId, 'key' => $key],
            [
                'group' => $group,
                'type' => $type,
                'is_encrypted' => $encrypted,
                'value' => self::encode($value, $type, $encrypted),
            ]
        );

        self::forget($branchId);
    }

    /** @param array<string, mixed> $values */
    public static function putMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'bool',
                is_int($value) => 'int',
                is_float($value) => 'float',
                is_array($value) => 'json',
                default => 'string',
            };

            self::put($key, $value, $group, $type);
        }
    }

    public static function forget(?int $branchId = null): void
    {
        $memo = self::cacheKey($branchId ?? self::branchId());
        cache()->forget($memo);
        unset(self::$loaded[$memo]);
    }

    private static function encode(mixed $value, string $type, bool $encrypted): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };

        return $encrypted ? Crypt::encryptString($raw) : $raw;
    }

    private static function decode(Setting $row): mixed
    {
        $raw = $row->value;

        if ($raw !== null && $row->is_encrypted) {
            try {
                $raw = Crypt::decryptString($raw);
            } catch (\Throwable) {
                // A rotated APP_KEY makes stored secrets unreadable; treat as unset
                // rather than taking the whole settings load down with it.
                return null;
            }
        }

        return match ($row->type) {
            'bool' => (bool) $raw,
            'int' => $raw === null ? null : (int) $raw,
            'float' => $raw === null ? null : (float) $raw,
            'json' => $raw === null ? null : json_decode($raw, true),
            default => $raw,
        };
    }
}
