<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtpCheck extends Model
{
    use BelongsToOrganization, HasFactory;

    public const RESULT_FULL    = 'full';
    public const RESULT_PARTIAL = 'partial';
    public const RESULT_NONE    = 'none';

    protected $fillable = [
        'organization_id',
        'source_document_id',
        'source_document_type',
        'product_id',
        'warehouse_id',
        'requested_quantity',
        'confirmed_quantity',
        'requested_date',
        'confirmed_date',
        'availability_breakdown',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity'    => 'decimal:4',
            'confirmed_quantity'    => 'decimal:4',
            'requested_date'        => 'date',
            'confirmed_date'        => 'date',
            'availability_breakdown' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeForDocument($query, string $type, int $id)
    {
        return $query->where('source_document_type', $type)
            ->where('source_document_id', $id);
    }
}
