<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChangeFreezeperiod extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'change_freeze_periods';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at'        => 'datetime',
            'ends_at'          => 'datetime',
            'affected_modules' => 'array',
            'bypass_roles'     => 'array',
            'is_active'        => 'boolean',
        ];
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Return true when this freeze is currently active (is_active flag is set
     * and the current time falls within [starts_at, ends_at]).
     */
    public function isActiveNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * Return true when this freeze applies to the given module name.
     * A freeze with scope='all' always affects every module.
     */
    public function affectsModule(string $module): bool
    {
        if ($this->scope === 'all') {
            return true;
        }

        $affected = $this->affected_modules ?? [];

        return in_array($module, $affected, true);
    }

    /**
     * Return true when the given user is allowed to bypass this freeze.
     * Super-admins always bypass. Users bypass when they hold a bypass role
     * or possess the bypass_permission.
     */
    public function canBypass(User $user): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        // Check bypass roles
        $bypassRoles = $this->bypass_roles ?? [];
        if (! empty($bypassRoles)) {
            foreach ($bypassRoles as $roleName) {
                if ($user->hasRole($roleName)) {
                    return true;
                }
            }
        }

        // Check bypass permission
        if ($this->bypass_permission !== null && $user->hasPermission($this->bypass_permission)) {
            return true;
        }

        return false;
    }
}
