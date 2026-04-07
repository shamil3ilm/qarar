<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialClosePeriod extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CLOSED      = 'closed';
    public const STATUS_REOPENED    = 'reopened';

    protected $fillable = [
        'organization_id',
        'financial_close_template_id',
        'fiscal_year',
        'period',
        'close_type',
        'status',
        'opened_at',
        'closed_at',
        'closed_by',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'opened_at'  => 'datetime',
            'closed_at'  => 'datetime',
            'due_date'   => 'date',
            'fiscal_year' => 'integer',
            'period'     => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FinancialCloseTemplate::class, 'financial_close_template_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(FinancialCloseTask::class)->orderBy('sort_order');
    }
}
