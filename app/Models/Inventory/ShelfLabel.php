<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Core\Branch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShelfLabel extends Model
{
    use BelongsToOrganization, HasFactory;

    public const LABEL_TYPE_STANDARD = 'standard';
    public const LABEL_TYPE_PROMOTIONAL = 'promotional';
    public const LABEL_TYPE_CLEARANCE = 'clearance';
    public const LABEL_TYPE_NEW_ARRIVAL = 'new_arrival';
    public const LABEL_TYPE_ORGANIC = 'organic';
    public const LABEL_TYPE_HALAL = 'halal';

    public const LABEL_SIZE_SMALL = 'small';
    public const LABEL_SIZE_STANDARD = 'standard';
    public const LABEL_SIZE_LARGE = 'large';
    public const LABEL_SIZE_SHELF_STRIP = 'shelf_strip';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'product_id',
        'variant_id',
        'product_name',
        'sku',
        'barcode_value',
        'price',
        'compare_at_price',
        'currency_code',
        'unit_label',
        'price_per_unit',
        'unit_measure_label',
        'aisle',
        'shelf',
        'position',
        'label_type',
        'label_size',
        'is_digital',
        'esl_device_id',
        'last_synced_at',
        'needs_reprint',
        'last_printed_at',
        'print_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'compare_at_price' => 'decimal:4',
            'price_per_unit' => 'decimal:4',
            'is_digital' => 'boolean',
            'last_synced_at' => 'datetime',
            'needs_reprint' => 'boolean',
            'last_printed_at' => 'datetime',
            'print_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // Business logic
    public function hasDiscount(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->price;
    }

    public function getDiscountPercentage(): float
    {
        if (!$this->hasDiscount()) {
            return 0;
        }

        return round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100, 1);
    }

    public function isDigital(): bool
    {
        return $this->is_digital;
    }

    public function needsReprint(): bool
    {
        return $this->needs_reprint;
    }

    public function markPrinted(): void
    {
        $this->update([
            'needs_reprint' => false,
            'last_printed_at' => now(),
            'print_count' => $this->print_count + 1,
        ]);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNeedsReprint($query)
    {
        return $query->where('needs_reprint', true)->where('is_digital', false);
    }

    public function scopeDigital($query)
    {
        return $query->where('is_digital', true);
    }

    public function scopeByBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByLabelType($query, string $type)
    {
        return $query->where('label_type', $type);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeInAisle($query, string $aisle)
    {
        return $query->where('aisle', $aisle);
    }
}
