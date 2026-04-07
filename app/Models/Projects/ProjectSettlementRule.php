<?php

declare(strict_types=1);

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSettlementRule extends Model
{
    use HasFactory;

    public const RECEIVER_COST_CENTER    = 'cost_center';
    public const RECEIVER_GL_ACCOUNT     = 'gl_account';
    public const RECEIVER_INTERNAL_ORDER = 'internal_order';
    public const RECEIVER_PROFIT_CENTER  = 'profit_center';

    protected $fillable = [
        'project_id',
        'wbs_element_id',
        'receiver_type',
        'receiver_id',
        'settlement_percentage',
    ];

    protected $casts = [
        'settlement_percentage' => 'decimal:2',
        'receiver_id'           => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function wbsElement(): BelongsTo
    {
        return $this->belongsTo(WbsElement::class);
    }
}
