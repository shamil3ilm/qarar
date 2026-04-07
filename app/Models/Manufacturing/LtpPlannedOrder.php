<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LtpPlannedOrder extends Model
{
    use HasFactory, HasUuid;

    public const TYPE_PRODUCTION = 'production';
    public const TYPE_PURCHASE   = 'purchase';

    protected $fillable = [
        'ltp_simulation_id',
        'product_id',
        'planned_order_type',
        'quantity',
        'unit_id',
        'planned_start',
        'planned_finish',
        'production_version_id',
        'vendor_id',
    ];

    protected $casts = [
        'quantity'      => 'decimal:4',
        'planned_start' => 'date',
        'planned_finish' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function simulation(): BelongsTo
    {
        return $this->belongsTo(LtpSimulation::class, 'ltp_simulation_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function productionVersion(): BelongsTo
    {
        return $this->belongsTo(ProductionVersion::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }
}
