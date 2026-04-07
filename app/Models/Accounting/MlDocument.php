<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MlDocument extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    public const TYPE_GOODS_RECEIPT = 'goods_receipt';
    public const TYPE_GOODS_ISSUE   = 'goods_issue';
    public const TYPE_INVOICE       = 'invoice';
    public const TYPE_TRANSFER      = 'transfer';
    public const TYPE_ADJUSTMENT    = 'adjustment';
    public const TYPE_CLOSING       = 'closing';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity'         => 'decimal:4',
            'standard_value'   => 'decimal:4',
            'actual_value'     => 'decimal:4',
            'price_difference' => 'decimal:4',
            'posting_date'     => 'date',
            'reference_id'     => 'integer',
        ];
    }

    public function materialLedgerRecord(): BelongsTo
    {
        return $this->belongsTo(MaterialLedgerRecord::class);
    }
}
