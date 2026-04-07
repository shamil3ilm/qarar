<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CycleCountSession extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'plan_id', 'warehouse_id', 'session_date',
        'counted_by', 'status', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'session_date'  => 'date',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
    ];

    public function plan(): BelongsTo      { return $this->belongsTo(CycleCountPlan::class, 'plan_id'); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function countedBy(): BelongsTo { return $this->belongsTo(User::class, 'counted_by'); }
    public function lines(): HasMany       { return $this->hasMany(CycleCountLine::class, 'cycle_count_session_id'); }
}
