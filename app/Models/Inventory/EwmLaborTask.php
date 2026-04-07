<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EwmLaborTask extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $table = 'ewm_labor_tasks';

    public const TASK_TYPE_PICK   = 'pick';
    public const TASK_TYPE_PUT    = 'put';
    public const TASK_TYPE_MOVE   = 'move';
    public const TASK_TYPE_COUNT  = 'count';
    public const TASK_TYPE_PACK   = 'pack';
    public const TASK_TYPE_LOAD   = 'load';
    public const TASK_TYPE_UNLOAD = 'unload';

    public const PRIORITY_URGENT = 'urgent';
    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_LOW    = 'low';

    public const STATUS_QUEUED      = 'queued';
    public const STATUS_ASSIGNED    = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'standard_minutes' => 'float',
            'actual_minutes'   => 'float',
            'assigned_at'      => 'datetime',
            'started_at'       => 'datetime',
            'completed_at'     => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function transferOrder(): BelongsTo
    {
        return $this->belongsTo(EwmTransferOrder::class, 'transfer_order_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
