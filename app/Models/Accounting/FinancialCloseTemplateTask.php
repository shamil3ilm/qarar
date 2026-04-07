<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialCloseTemplateTask extends Model
{
    use HasUuid;

    public const TYPE_JOURNAL        = 'journal';
    public const TYPE_RECONCILIATION = 'reconciliation';
    public const TYPE_REPORT         = 'report';
    public const TYPE_APPROVAL       = 'approval';
    public const TYPE_MANUAL         = 'manual';

    protected $fillable = [
        'financial_close_template_id',
        'task_name',
        'description',
        'task_type',
        'sort_order',
        'estimated_duration_hours',
        'required_role',
    ];

    protected function casts(): array
    {
        return [
            'sort_order'                 => 'integer',
            'estimated_duration_hours'   => 'decimal:1',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FinancialCloseTemplate::class, 'financial_close_template_id');
    }
}
