<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QInfoRecord extends Model
{
    use HasFactory, HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $table = 'q_info_records';

    public const INSPECTION_GOODS_RECEIPT = 'goods_receipt';
    public const INSPECTION_IN_PROCESS    = 'in_process';
    public const INSPECTION_FINAL         = 'final';
    public const INSPECTION_DELIVERY      = 'delivery';
    public const INSPECTION_RETURNS       = 'returns';

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'product_id',
        'inspection_type',
        'skip_lot_plan_id',
        'quality_plan_id',
        'is_active',
        'release_required',
        'cert_required',
        'cert_type',
        'inspection_interval_days',
        'last_inspection_date',
        'next_inspection_date',
        'notes',
    ];

    protected $casts = [
        'is_active'                => 'boolean',
        'release_required'         => 'boolean',
        'cert_required'            => 'boolean',
        'inspection_interval_days' => 'integer',
        'last_inspection_date'     => 'date',
        'next_inspection_date'     => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function skipLotPlan(): BelongsTo
    {
        return $this->belongsTo(SkipLotSamplingPlan::class, 'skip_lot_plan_id');
    }

    public function qualityPlan(): BelongsTo
    {
        return $this->belongsTo(QualityPlan::class, 'quality_plan_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForVendor(Builder $query, int $vendorId): Builder
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function isDue(): bool
    {
        if ($this->next_inspection_date === null) {
            return true;
        }

        return $this->next_inspection_date->lte(Carbon::today());
    }

    public function updateNextInspectionDate(): void
    {
        if ($this->inspection_interval_days === null) {
            return;
        }

        $this->last_inspection_date = Carbon::today();
        $this->next_inspection_date = Carbon::today()->addDays($this->inspection_interval_days);
        $this->save();
    }
}
