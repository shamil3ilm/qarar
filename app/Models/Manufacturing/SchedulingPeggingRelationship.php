<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchedulingPeggingRelationship extends Model
{
    use HasFactory, HasUuid;

    /** Finish-to-Start: successor starts after predecessor finishes */
    public const TYPE_FS = 'fs';
    /** Start-to-Start: successor starts after predecessor starts */
    public const TYPE_SS = 'ss';
    /** Finish-to-Finish: successor finishes after predecessor finishes */
    public const TYPE_FF = 'ff';
    /** Start-to-Finish: successor finishes after predecessor starts */
    public const TYPE_SF = 'sf';

    protected $fillable = [
        'predecessor_operation_id',
        'successor_operation_id',
        'relationship_type',
        'lag_minutes',
    ];

    protected $casts = [
        'lag_minutes' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(SchedulingOperation::class, 'predecessor_operation_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(SchedulingOperation::class, 'successor_operation_id');
    }
}
