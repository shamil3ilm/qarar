<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\ERP\MissingTenantException;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

trait BelongsToOrganization
{
    /**
     * When true, the tenant guard is suspended for the duration of the closure.
     * Toggle only via withoutTenantCheck() — never set directly.
     */
    private static bool $bypassTenantCheck = false;

    protected static function bootBelongsToOrganization(): void
    {
        // Auto-scope all queries to the authenticated user's organisation.
        static::addGlobalScope('organization', function (Builder $builder): void {
            if ($user = auth()->user()) {
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.organization_id", $user->organization_id);
            }
        });

        // Auto-set organization_id on create; hard-fail if still missing afterwards.
        static::creating(function (Model $model): void {
            if (empty($model->organization_id) && auth()->check()) {
                $model->organization_id = auth()->user()->organization_id;
            }

            if (empty($model->organization_id) && !static::$bypassTenantCheck) {
                throw MissingTenantException::forModel(get_class($model));
            }
        });
    }

    /**
     * Execute a callback with the tenant guard suspended.
     *
     * Use only for platform seeders, migrations, and system records where no
     * authenticated user exists. The reason is logged as a warning in production.
     *
     * @template T
     * @param  callable(): T  $callback
     * @param  string         $reason  Mandatory explanation — written to audit log.
     * @return T
     */
    public static function withoutTenantCheck(callable $callback, string $reason = ''): mixed
    {
        if (app()->isProduction() || app()->environment('staging')) {
            Log::warning('BelongsToOrganization: tenant check bypassed', [
                'reason'  => $reason ?: 'no reason provided',
                'caller'  => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [],
                'user_id' => auth()->id(),
            ]);
        }

        static::$bypassTenantCheck = true;

        try {
            return $callback();
        } finally {
            static::$bypassTenantCheck = false;
        }
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->withoutGlobalScope('organization')
            ->where('organization_id', $organizationId);
    }

    public function scopeWithoutOrganizationScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('organization');
    }
}
