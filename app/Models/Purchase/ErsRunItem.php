<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErsRunItem extends Model
{
    use BelongsToOrganization;

    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_SKIPPED   = 'skipped';

    protected $fillable = [
        'organization_id',
        'ers_run_id',
        'goods_receipt_id',
        'bill_id',
        'vendor_id',
        'gross_amount',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:4',
        ];
    }

    public function ersRun(): BelongsTo
    {
        return $this->belongsTo(ErsRun::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'vendor_id');
    }
}
