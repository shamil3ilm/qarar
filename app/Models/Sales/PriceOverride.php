<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceOverride extends Model
{
    use HasFactory, HasUuid, BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'document_type', 'document_id', 'line_item_id',
        'product_id', 'variant_id', 'original_price', 'override_price', 'cost_price',
        'price_difference', 'discount_percent', 'quantity', 'total_impact',
        'override_type', 'reason_code', 'reason', 'notes', 'approval_status',
        'approved_by', 'approved_at', 'approval_notes', 'policy_id', 'customer_id',
        'margin_before', 'margin_after', 'created_by',
    ];

    protected $casts = [
        'original_price' => 'decimal:4',
        'override_price' => 'decimal:4',
        'cost_price' => 'decimal:4',
        'price_difference' => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'quantity' => 'decimal:4',
        'total_impact' => 'decimal:2',
        'margin_before' => 'decimal:2',
        'margin_after' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Product::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(PriceOverridePolicy::class, 'policy_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
