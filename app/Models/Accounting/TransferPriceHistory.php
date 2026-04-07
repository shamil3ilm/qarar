<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferPriceHistory extends Model
{
    protected $fillable = [
        'transfer_price_id',
        'changed_by',
        'old_price',
        'new_price',
        'change_reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_price'  => 'decimal:4',
            'new_price'  => 'decimal:4',
            'changed_at' => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function transferPrice(): BelongsTo
    {
        return $this->belongsTo(TransferPrice::class, 'transfer_price_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
