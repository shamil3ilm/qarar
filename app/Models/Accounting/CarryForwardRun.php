<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarryForwardRun extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'accounts_processed'   => 'integer',
            'total_amount_carried' => 'decimal:4',
            'executed_at'          => 'datetime',
        ];
    }

    public const RUN_TYPE_BALANCE_SHEET = 'balance_sheet';
    public const RUN_TYPE_PROFIT_LOSS   = 'profit_loss';
    public const RUN_TYPE_BOTH          = 'both';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    public function fromFiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'from_fiscal_year_id');
    }

    public function toFiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'to_fiscal_year_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
}
