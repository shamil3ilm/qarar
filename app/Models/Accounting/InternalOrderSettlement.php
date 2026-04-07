<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalOrderSettlement extends Model
{
    public const RECEIVER_COST_CENTER  = 'cost_center';
    public const RECEIVER_GL_ACCOUNT   = 'gl_account';
    public const RECEIVER_PROJECT_WBS  = 'project_wbs';
    public const RECEIVER_PROFIT_CENTER = 'profit_center';

    protected $fillable = [
        'internal_order_id',
        'receiver_type',
        'receiver_id',
        'settlement_percentage',
    ];

    protected function casts(): array
    {
        return [
            'settlement_percentage' => 'decimal:2',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function internalOrder(): BelongsTo
    {
        return $this->belongsTo(InternalOrder::class, 'internal_order_id');
    }
}
