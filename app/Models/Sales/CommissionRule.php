<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    use HasFactory, BelongsToOrganization, HasUuid;

    public const TYPE_FLAT             = 'flat';
    public const TYPE_TIERED           = 'tiered';
    public const TYPE_PRODUCT_CATEGORY = 'product_category';
    public const TYPE_CUSTOMER_GROUP   = 'customer_group';

    protected $fillable = [
        'organization_id',
        'commission_master_id',
        'rule_type',
        'condition_field',
        'condition_value',
        'rate',
        'tier_from',
        'tier_to',
        'priority',
    ];

    protected $casts = [
        'rate'      => 'decimal:4',
        'tier_from' => 'decimal:4',
        'tier_to'   => 'decimal:4',
        'priority'  => 'integer',
    ];

    public function master(): BelongsTo
    {
        return $this->belongsTo(CommissionMaster::class, 'commission_master_id');
    }
}
