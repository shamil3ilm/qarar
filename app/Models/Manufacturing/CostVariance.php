<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostVariance extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'standard_material_cost' => 'decimal:4',
        'actual_material_cost'   => 'decimal:4',
        'standard_labor_cost'    => 'decimal:4',
        'actual_labor_cost'      => 'decimal:4',
        'standard_overhead_cost' => 'decimal:4',
        'actual_overhead_cost'   => 'decimal:4',
        'total_standard'         => 'decimal:4',
        'total_actual'           => 'decimal:4',
        'total_variance'         => 'decimal:4',
        'variance_pct'           => 'decimal:2',
    ];

    // Relationships

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function costingVersion(): BelongsTo
    {
        return $this->belongsTo(CostingVersion::class, 'costing_version_id');
    }

    // Helpers

    public function isFavorable(): bool
    {
        return (float) $this->total_variance < 0;
    }

    public function isAdverse(): bool
    {
        return (float) $this->total_variance > 0;
    }

    public function getMaterialVariance(): float
    {
        return (float) bcsub((string) $this->actual_material_cost, (string) $this->standard_material_cost, 4);
    }

    public function getLaborVariance(): float
    {
        return (float) bcsub((string) $this->actual_labor_cost, (string) $this->standard_labor_cost, 4);
    }

    public function getOverheadVariance(): float
    {
        return (float) bcsub((string) $this->actual_overhead_cost, (string) $this->standard_overhead_cost, 4);
    }
}
