<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureFlagRolloutLog extends Model
{
    protected $guarded = ['id'];

    /**
     * Immutable log — no updated_at column.
     */
    const UPDATED_AT = null;

    protected $casts = [
        'detail'     => 'array',
        'created_at' => 'datetime',
    ];

    public const ACTION_ENABLED                = 'enabled';
    public const ACTION_DISABLED               = 'disabled';
    public const ACTION_TARGET_ADDED           = 'target_added';
    public const ACTION_TARGET_REMOVED         = 'target_removed';
    public const ACTION_ROLLOUT_PERCENTAGE_SET = 'rollout_percentage_set';

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
