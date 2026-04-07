<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchedulingBoard extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'horizon_days',
        'work_center_ids',
    ];

    protected $casts = [
        'horizon_days'    => 'integer',
        'work_center_ids' => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function operations(): HasMany
    {
        return $this->hasMany(SchedulingOperation::class)
            ->orderBy('planned_start');
    }
}
