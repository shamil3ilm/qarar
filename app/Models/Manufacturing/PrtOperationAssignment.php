<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrtOperationAssignment extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'production_resource_tool_id',
        'routing_operation_id',
        'work_order_id',
        'usage_type',
        'quantity_required',
        'assigned_at',
        'released_at',
        'status',
    ];

    protected $casts = [
        'quantity_required' => 'integer',
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    // Relationships

    public function productionResourceTool(): BelongsTo
    {
        return $this->belongsTo(ProductionResourceTool::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function routingOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class);
    }
}
