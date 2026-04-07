<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverySplitRule extends Model
{
    use BelongsToOrganization, HasFactory;

    public const CRITERIA_WAREHOUSE    = 'warehouse';
    public const CRITERIA_DELIVERY_DATE = 'delivery_date';
    public const CRITERIA_ROUTE        = 'route';
    public const CRITERIA_WEIGHT       = 'weight';
    public const CRITERIA_VOLUME       = 'volume';

    public const APPLIES_ALL_CUSTOMERS    = 'all_customers';
    public const APPLIES_CUSTOMER_GROUP   = 'customer_group';
    public const APPLIES_SPECIFIC_CUSTOMER = 'specific_customer';

    protected $fillable = [
        'organization_id',
        'rule_name',
        'split_criteria',
        'applies_to',
        'applies_to_id',
        'allow_partial_delivery',
        'minimum_delivery_quantity_pct',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allow_partial_delivery'        => 'boolean',
            'minimum_delivery_quantity_pct' => 'decimal:2',
            'is_active'                     => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function appliesTo(int $customerId, ?int $customerGroupId): bool
    {
        return match ($this->applies_to) {
            self::APPLIES_ALL_CUSTOMERS     => true,
            self::APPLIES_SPECIFIC_CUSTOMER => $this->applies_to_id === $customerId,
            self::APPLIES_CUSTOMER_GROUP    => $this->applies_to_id === $customerGroupId,
            default                         => false,
        };
    }
}
