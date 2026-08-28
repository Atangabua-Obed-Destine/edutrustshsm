<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Records create / update / delete of a model into the audit trail.
 *
 * The trail was previously written only by ~59 hand-placed AuditLog::log()
 * calls, all in finance controllers — so the highest-value records (marks,
 * grades, promotions, enrolments, fee collection) had no trail at all, while
 * expense categories did.
 *
 * Domain events that are not a plain Eloquent write ("posted", "revised",
 * "closed") should still call AuditLog::log() explicitly; this trait covers
 * the mechanical CRUD underneath them.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->recordAudit('created', null, $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function ($model) {
            $changes = $model->auditableAttributes($model->getChanges());

            // Nothing meaningful changed (e.g. only a timestamp) — don't log noise.
            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            $model->recordAudit('updated', $model->auditableAttributes($original), $changes);
        });

        static::deleted(function ($model) {
            $model->recordAudit('deleted', $model->auditableAttributes($model->getOriginal()), null);
        });
    }

    /**
     * Attributes that never belong in an audit record: secrets, and the
     * bookkeeping columns that change on every write.
     *
     * @return array<int, string>
     */
    protected function auditExcluded(): array
    {
        return array_merge(
            ['password', 'remember_token', 'invite_token', 'updated_at', 'created_at'],
            $this->hidden ?? []
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableAttributes(array $attributes): array
    {
        return array_diff_key($attributes, array_flip($this->auditExcluded()));
    }

    protected function recordAudit(string $action, ?array $old, ?array $new): void
    {
        AuditLog::log($action, static::class, $this->getKey(), $old, $new);
    }
}
