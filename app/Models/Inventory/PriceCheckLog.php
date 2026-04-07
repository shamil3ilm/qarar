<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Core\Branch;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceCheckLog extends Model
{
    use BelongsToOrganization, HasFactory;

    public const SCAN_BARCODE = 'barcode';
    public const SCAN_QR = 'qr';
    public const SCAN_RFID = 'rfid';
    public const SCAN_NFC = 'nfc';
    public const SCAN_MANUAL = 'manual';
    public const SCAN_SKU = 'sku';

    public const STOCK_IN_STOCK = 'in_stock';
    public const STOCK_LOW_STOCK = 'low_stock';
    public const STOCK_OUT_OF_STOCK = 'out_of_stock';

    public const ERROR_NOT_FOUND = 'not_found';
    public const ERROR_INACTIVE = 'inactive';
    public const ERROR_NO_PRICE = 'no_price';
    public const ERROR_SCAN_ERROR = 'scan_error';

    protected $fillable = [
        'organization_id',
        'station_id',
        'branch_id',
        'scan_type',
        'scan_value',
        'scan_successful',
        'product_id',
        'variant_id',
        'product_name',
        'product_sku',
        'displayed_price',
        'original_price',
        'currency_code',
        'has_promotion',
        'promotion_name',
        'promotion_discount',
        'contact_id',
        'loyalty_tier',
        'stock_available',
        'stock_status',
        'error_type',
        'error_message',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'scan_successful' => 'boolean',
            'displayed_price' => 'decimal:4',
            'original_price' => 'decimal:4',
            'has_promotion' => 'boolean',
            'promotion_discount' => 'decimal:2',
            'stock_available' => 'decimal:4',
            'scanned_at' => 'datetime',
        ];
    }

    // Relationships
    public function station(): BelongsTo
    {
        return $this->belongsTo(PriceCheckStation::class, 'station_id');
    }

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

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    // Business logic
    public function wasSuccessful(): bool
    {
        return $this->scan_successful;
    }

    public function hadPromotion(): bool
    {
        return $this->has_promotion;
    }

    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('scan_successful', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('scan_successful', false);
    }

    public function scopeByStation($query, int $stationId)
    {
        return $query->where('station_id', $stationId);
    }

    public function scopeByBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByErrorType($query, string $errorType)
    {
        return $query->where('error_type', $errorType);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('scanned_at', [$startDate, $endDate]);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('scanned_at', '>=', now()->subHours($hours));
    }
}
