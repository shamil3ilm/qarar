<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScrapReport extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const CAUSE_DEFECT = 'defect';
    public const CAUSE_DAMAGE = 'damage';
    public const CAUSE_OBSOLETE = 'obsolete';
    public const CAUSE_PROCESS_LOSS = 'process_loss';
    public const CAUSE_MACHINE_FAILURE = 'machine_failure';
    public const CAUSE_OTHER = 'other';

    protected $fillable = [
        'organization_id',
        'work_order_id',
        'product_id',
        'warehouse_id',
        'scrap_date',
        'scrap_quantity',
        'unit_of_measure',
        'scrap_cause',
        'scrap_code',
        'description',
        'estimated_value',
        'is_recoverable',
        'recovery_value',
        'gl_posted',
        'gl_posted_at',
        'reported_by',
    ];

    protected $casts = [
        'scrap_date' => 'date',
        'scrap_quantity' => 'decimal:4',
        'estimated_value' => 'decimal:4',
        'is_recoverable' => 'boolean',
        'recovery_value' => 'decimal:4',
        'gl_posted' => 'boolean',
        'gl_posted_at' => 'datetime',
    ];

    // Relationships

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    // Scopes

    public function scopeForWorkOrder(Builder $query, int $workOrderId): Builder
    {
        return $query->where('work_order_id', $workOrderId);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeUnposted(Builder $query): Builder
    {
        return $query->where('gl_posted', false);
    }

    // Helpers

    public function getNetLoss(): float
    {
        return (float) $this->estimated_value - (float) $this->recovery_value;
    }

    public function markGlPosted(): void
    {
        $this->update([
            'gl_posted' => true,
            'gl_posted_at' => now(),
        ]);
    }
}
