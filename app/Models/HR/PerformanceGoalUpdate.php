<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceGoalUpdate extends Model
{
    public const UPDATED_AT = null; // only created_at

    protected $fillable = [
        'performance_goal_id',
        'updated_by',
        'progress_percent',
        'notes',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'created_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------------

    public function goal(): BelongsTo
    {
        return $this->belongsTo(PerformanceGoal::class, 'performance_goal_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
