<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CycleCountPlan extends Model
{
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'organization_id', 'plan_name', 'warehouse_id',
        'count_frequency', 'products_per_day', 'scheduled_date', 'status',
    ];

    protected $casts = ['scheduled_date' => 'date'];

    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function sessions(): HasMany    { return $this->hasMany(CycleCountSession::class, 'plan_id'); }
}
