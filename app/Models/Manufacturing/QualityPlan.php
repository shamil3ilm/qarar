<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityPlan extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STAGE_GOODS_RECEIPT = 'goods_receipt';
    public const STAGE_PRODUCTION = 'production';
    public const STAGE_PRE_SHIPMENT = 'pre_shipment';
    public const STAGE_IN_PROCESS = 'in_process';
    public const STAGE_FINAL = 'final';

    protected $fillable = [
        'organization_id',
        'name',
        'product_id',
        'product_category_id',
        'inspection_stage',
        'is_active',
        'description',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships

    public function characteristics(): HasMany
    {
        return $this->hasMany(QualityPlanCharacteristic::class)->orderBy('sort_order');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'product_category_id');
    }

    public function inspectionLots(): HasMany
    {
        return $this->hasMany(InspectionLot::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForStage($query, string $stage)
    {
        return $query->where('inspection_stage', $stage);
    }

    // Helper Methods

    /**
     * Determine whether this plan is applicable for the given product.
     * A plan is applicable if it is active and either has no product restriction
     * or is directly linked to the given product.
     */
    public function isApplicableFor(int $productId): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->product_id === null) {
            return true;
        }

        return $this->product_id === $productId;
    }

    /**
     * Get human-readable stage label.
     */
    public function getStageLabelAttribute(): string
    {
        return match ($this->inspection_stage) {
            self::STAGE_GOODS_RECEIPT => 'Goods Receipt',
            self::STAGE_PRODUCTION => 'Production',
            self::STAGE_PRE_SHIPMENT => 'Pre-Shipment',
            self::STAGE_IN_PROCESS => 'In-Process',
            self::STAGE_FINAL => 'Final',
            default => ucfirst(str_replace('_', ' ', $this->inspection_stage)),
        };
    }
}
