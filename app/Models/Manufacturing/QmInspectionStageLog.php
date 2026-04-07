<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QmInspectionStageLog extends Model
{
    protected $table = 'qm_inspection_stage_log';

    public const STAGE_TIGHTENED = 'tightened';
    public const STAGE_NORMAL    = 'normal';
    public const STAGE_REDUCED   = 'reduced';
    public const STAGE_SKIP      = 'skip';

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'rule_id',
        'product_id',
        'supplier_id',
        'current_stage',
        'consecutive_pass',
        'consecutive_fail',
        'last_evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'consecutive_pass'   => 'integer',
            'consecutive_fail'   => 'integer',
            'last_evaluated_at'  => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(QmDynamicModificationRule::class, 'rule_id');
    }
}
