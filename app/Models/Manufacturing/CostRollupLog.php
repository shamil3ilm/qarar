<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostRollupLog extends Model
{
    use BelongsToOrganization, HasFactory;

    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'run_at'            => 'datetime',
        'created_at'        => 'datetime',
        'products_costed'   => 'integer',
        'levels_processed'  => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function costVersion(): BelongsTo
    {
        return $this->belongsTo(CostVersion::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}
