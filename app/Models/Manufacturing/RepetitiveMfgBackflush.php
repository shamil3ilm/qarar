<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepetitiveMfgBackflush extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'repetitive_mfg_schedule_id',
        'backflush_date',
        'quantity_produced',
        'quantity_scrapped',
        'component_movements',
        'labor_time_minutes',
        'created_by',
    ];

    protected $casts = [
        'backflush_date'       => 'datetime',
        'quantity_produced'    => 'decimal:4',
        'quantity_scrapped'    => 'decimal:4',
        'component_movements'  => 'array',
        'labor_time_minutes'   => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RepetitiveMfgSchedule::class, 'repetitive_mfg_schedule_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
