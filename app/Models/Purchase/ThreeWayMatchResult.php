<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreeWayMatchResult extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $table = 'three_way_match_results';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'po_quantity' => 'decimal:4',
            'gr_quantity' => 'decimal:4',
            'invoice_quantity' => 'decimal:4',
            'po_unit_price' => 'decimal:4',
            'invoice_unit_price' => 'decimal:4',
            'quantity_match' => 'boolean',
            'price_match' => 'boolean',
            'variance_amount' => 'decimal:4',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'po_line_id');
    }

    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'gr_line_id');
    }

    public function isFullyMatched(): bool
    {
        return $this->match_status === 'matched';
    }

    public function hasVariance(): bool
    {
        return in_array($this->match_status, ['quantity_variance', 'price_variance'], true);
    }

    public function scopeForBill($query, int $billId)
    {
        return $query->where('bill_id', $billId);
    }

    public function scopeUnmatched($query)
    {
        return $query->whereNotIn('match_status', ['matched']);
    }
}
