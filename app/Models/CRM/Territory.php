<?php

declare(strict_types=1);

namespace App\Models\CRM;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\HR\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Territory extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    // Territory type constants
    public const TYPE_GLOBAL      = 'global';
    public const TYPE_REGION      = 'region';
    public const TYPE_COUNTRY     = 'country';
    public const TYPE_STATE       = 'state';
    public const TYPE_CITY        = 'city';
    public const TYPE_POSTAL_ZONE = 'postal_zone';
    public const TYPE_CUSTOM      = 'custom';

    // Status constants
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'organization_id',
        'parent_id',
        'name',
        'code',
        'description',
        'territory_type',
        'country_code',
        'state_code',
        'postal_codes',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'postal_codes' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Territory::class, 'parent_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TerritoryAssignment::class);
    }

    public function routingRules(): HasMany
    {
        return $this->hasMany(TerritoryRoutingRule::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Return the current primary owner of this territory (role = owner, effective today).
     */
    public function getOwner(): ?Employee
    {
        $assignment = $this->assignments()
            ->where('role', TerritoryAssignment::ROLE_OWNER)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now()->toDateString());
            })
            ->with('employee')
            ->latest('effective_from')
            ->first();

        return $assignment?->employee;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('territory_type', $type);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }
}
