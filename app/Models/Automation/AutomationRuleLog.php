<?php

declare(strict_types=1);

namespace App\Models\Automation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRuleLog extends Model
{
    use HasFactory;

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $guarded = ['id'];

    protected $casts = [
        'conditions_matched' => 'array',
        'actions_executed'   => 'array',
        'execution_time_ms'  => 'integer',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }

    public function scopeForRule($query, int $ruleId)
    {
        return $query->where('rule_id', $ruleId);
    }
}