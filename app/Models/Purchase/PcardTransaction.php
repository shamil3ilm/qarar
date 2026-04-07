<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PcardTransaction extends Model
{
    use BelongsToOrganization, HasUuid;

    public const STATUS_UNRECONCILED = 'unreconciled';
    public const STATUS_RECONCILED = 'reconciled';
    public const STATUS_DISPUTED = 'disputed';

    protected $fillable = [
        'organization_id',
        'pcard_statement_id',
        'transaction_date',
        'merchant_name',
        'merchant_category_code',
        'amount',
        'currency',
        'gl_account_id',
        'cost_center_id',
        'status',
        'receipt_attached',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:4',
            'receipt_attached' => 'boolean',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(PcardStatement::class, 'pcard_statement_id');
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function isReconciled(): bool
    {
        return $this->status === self::STATUS_RECONCILED;
    }
}
