<?php

namespace App\Services\EdutrustPay;

use App\Models\Branch;
use App\Models\EdutrustPaySetting;
use Illuminate\Support\Collection;

/**
 * Where the EdutrustPay connection settings actually come from.
 *
 * Two sources, in this order:
 *
 *   1. The database — set through the settings screen.
 *   2. config/edutrustpay.php, which reads .env.
 *
 * The database wins, and .env remains a working fallback so that a deployment
 * configured before the screen existed keeps reporting without anyone touching
 * it. Nothing that worked yesterday stops working today.
 *
 * Everything that needs credentials goes through here rather than reading
 * config() directly, so there is one place that knows the precedence — and one
 * place to look when somebody asks why a key is not being picked up.
 */
class SettingsResolver
{
    /**
     * Resolved settings for a branch, or null when it is not configured at all.
     *
     * @return array{
     *     branch_id: int, enabled: bool, endpoint: string,
     *     institution_ref: string, key_id: string, secret: string, source: string
     * }|null
     */
    public function forBranch(int $branchId): ?array
    {
        $row = EdutrustPaySetting::where('branch_id', $branchId)->first();

        if ($row && $row->isConfigured()) {
            return [
                'branch_id' => $branchId,
                'enabled' => (bool) $row->enabled,
                'endpoint' => (string) $row->endpoint,
                'institution_ref' => (string) $row->institution_ref,
                'key_id' => (string) $row->key_id,
                'secret' => (string) $row->secret(),
                'source' => 'database',
            ];
        }

        $fallback = (array) (config('edutrustpay.branches')[$branchId] ?? []);

        if (blank($fallback['ref'] ?? null) || blank($fallback['key_id'] ?? null) || blank($fallback['secret'] ?? null)) {
            return null;
        }

        return [
            'branch_id' => $branchId,
            'enabled' => (bool) config('edutrustpay.enabled', false),
            'endpoint' => (string) config('edutrustpay.endpoint'),
            'institution_ref' => (string) $fallback['ref'],
            'key_id' => (string) $fallback['key_id'],
            'secret' => (string) $fallback['secret'],
            'source' => 'env',
        ];
    }

    /**
     * Every branch that can currently report.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function configured(): Collection
    {
        return Branch::query()
            ->pluck('id')
            ->map(fn ($id) => $this->forBranch((int) $id))
            ->filter()
            // A branch whose settings exist but are switched off is configured
            // and deliberately quiet, which is not the same as unconfigured.
            ->filter(fn (array $s) => $s['enabled'])
            ->values();
    }

    /**
     * @return array<int, int>
     */
    public function configuredBranchIds(): array
    {
        return $this->configured()->pluck('branch_id')->map('intval')->all();
    }
}
