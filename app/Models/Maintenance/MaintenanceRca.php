<?php

declare(strict_types=1);

namespace App\Models\Maintenance;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRca extends Model
{
    use HasUuid;

    protected $table = 'maintenance_root_cause_analyses';

    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED      = 'closed';

    protected $fillable = [
        'organization_id',
        'maintenance_order_id',
        'equipment_id',
        'fault_code_id',
        'rca_method',
        'whys',
        'root_cause',
        'contributing_factors',
        'corrective_actions',
        'preventive_actions',
        'status',
        'assigned_to',
        'target_date',
        'closed_date',
    ];

    protected $casts = [
        'whys'        => 'array',
        'target_date' => 'date',
        'closed_date' => 'date',
    ];

    public function faultCode(): BelongsTo
    {
        return $this->belongsTo(MaintenanceFaultCode::class, 'fault_code_id');
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }
}
