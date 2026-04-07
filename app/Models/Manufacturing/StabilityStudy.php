<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StabilityStudy extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    public const TYPE_REAL_TIME    = 'real_time';
    public const TYPE_ACCELERATED  = 'accelerated';
    public const TYPE_INTERMEDIATE = 'intermediate';

    public const STATUS_PLANNED       = 'planned';
    public const STATUS_ACTIVE        = 'active';
    public const STATUS_COMPLETED     = 'completed';
    public const STATUS_DISCONTINUED  = 'discontinued';

    protected $fillable = [
        'organization_id',
        'study_number',
        'product_id',
        'inventory_batch_id',
        'study_type',
        'status',
        'start_date',
        'planned_end_date',
        'storage_condition',
        'protocol_reference',
        'notes',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'planned_end_date' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function timePoints(): HasMany
    {
        return $this->hasMany(StabilityStudyTimePoint::class)->orderBy('scheduled_date');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
