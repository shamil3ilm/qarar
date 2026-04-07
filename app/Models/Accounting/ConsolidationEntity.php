<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsolidationEntity extends Model
{
    use HasFactory;

    public const METHOD_FULL          = 'full';
    public const METHOD_PROPORTIONAL  = 'proportional';
    public const METHOD_EQUITY        = 'equity';

    protected $fillable = [
        'consolidation_group_id',
        'entity_organization_id',
        'name',
        'ownership_percent',
        'consolidation_method',
        'local_currency',
    ];

    protected function casts(): array
    {
        return [
            'ownership_percent' => 'decimal:2',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ConsolidationGroup::class, 'consolidation_group_id');
    }

    public function entityOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'entity_organization_id');
    }

    public function consolidatedBalances(): HasMany
    {
        return $this->hasMany(ConsolidatedBalance::class, 'entity_organization_id', 'entity_organization_id');
    }

    /**
     * Return the effective ownership multiplier for balance consolidation.
     */
    public function getOwnershipMultiplier(): float
    {
        return match ($this->consolidation_method) {
            self::METHOD_PROPORTIONAL => (float) $this->ownership_percent / 100,
            default                   => 1.0, // full and equity use 100% of balances
        };
    }
}
