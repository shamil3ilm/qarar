<?php

declare(strict_types=1);

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkActivityRelationship extends Model
{
    use HasFactory;

    public const TYPE_FINISH_TO_START  = 'finish_to_start';
    public const TYPE_START_TO_START   = 'start_to_start';
    public const TYPE_FINISH_TO_FINISH = 'finish_to_finish';
    public const TYPE_START_TO_FINISH  = 'start_to_finish';

    protected $fillable = [
        'predecessor_activity_id',
        'successor_activity_id',
        'relationship_type',
        'lag_days',
    ];

    protected $casts = [
        'lag_days' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(NetworkActivity::class, 'predecessor_activity_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(NetworkActivity::class, 'successor_activity_id');
    }
}
