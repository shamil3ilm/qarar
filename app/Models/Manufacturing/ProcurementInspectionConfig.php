<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementInspectionConfig extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'product_id',
        'vendor_id',
        'inspection_required',
        'sampling_percentage',
        'auto_approve_below_defect_rate',
        'quality_plan_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'inspection_required'           => 'boolean',
            'sampling_percentage'           => 'decimal:2',
            'auto_approve_below_defect_rate' => 'decimal:2',
            'is_active'                     => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function qualityPlan(): BelongsTo
    {
        return $this->belongsTo(QualityPlan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function calculateQuantityToInspect(float $quantityReceived): float
    {
        return ceil($quantityReceived * ($this->sampling_percentage / 100));
    }
}
