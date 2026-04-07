<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfitCenter extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use HasAuditTrail;
    use SoftDeletes;

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'organization_id',
        'parent_id',
        'code',
        'name',
        'description',
        'manager_id',
        'status',
        'valid_from',
        'valid_to',
        'gl_account_id',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to'   => 'date',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProfitCenter::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProfitCenter::class, 'parent_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // ----------------------------------------------------------------
    // Business methods
    // ----------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Build the breadcrumb path using the parent chain.
     */
    public function getFullPath(): string
    {
        $parts   = [$this->code];
        $current = $this->parent;

        while ($current !== null) {
            array_unshift($parts, $current->code);
            $current = $current->parent;
        }

        return implode(' / ', $parts);
    }
}
