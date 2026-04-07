<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqQuoteLine extends Model
{
    use HasFactory;

    protected $table = 'rfq_quote_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'quantity' => 'decimal:4',
            'discount_pct' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:4',
            'delivery_days' => 'integer',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(RfqQuote::class, 'rfq_quote_id');
    }

    public function rfqItem(): BelongsTo
    {
        return $this->belongsTo(RfqItem::class, 'rfq_item_id');
    }
}
