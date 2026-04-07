<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkipLotDecision extends Model
{
    use HasUuid;
    use BelongsToOrganization;

    public const LEVEL_SKIP_LOT  = 'skip_lot';
    public const LEVEL_REDUCED    = 'reduced';
    public const LEVEL_NORMAL     = 'normal';
    public const LEVEL_TIGHTENED  = 'tightened';
    public const LEVEL_REJECTED   = 'rejected';

    protected $fillable = [
        'organization_id',
        'skip_lot_sampling_plan_id',
        'vendor_id',
        'product_id',
        'current_level',
        'lots_inspected_at_level',
        'consecutive_accepted',
        'consecutive_rejected',
        'last_inspection_lot_id',
        'last_evaluated_at',
    ];

    protected $casts = [
        'lots_inspected_at_level' => 'integer',
        'consecutive_accepted'    => 'integer',
        'consecutive_rejected'    => 'integer',
        'last_evaluated_at'       => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SkipLotSamplingPlan::class, 'skip_lot_sampling_plan_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lastInspectionLot(): BelongsTo
    {
        return $this->belongsTo(InspectionLot::class, 'last_inspection_lot_id');
    }
}
