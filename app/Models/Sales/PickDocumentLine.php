<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickDocumentLine extends Model
{
    protected $fillable = [
        'pick_document_id',
        'delivery_document_line_id',
        'required_quantity',
        'picked_quantity',
        'storage_bin',
        'status',
    ];

    protected $casts = [
        'required_quantity' => 'decimal:4',
        'picked_quantity'   => 'decimal:4',
    ];

    public function pickDocument(): BelongsTo
    {
        return $this->belongsTo(PickDocument::class);
    }

    public function deliveryDocumentLine(): BelongsTo
    {
        return $this->belongsTo(DeliveryDocumentLine::class);
    }
}
