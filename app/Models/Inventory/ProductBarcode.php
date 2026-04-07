<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBarcode extends Model
{
    use BelongsToOrganization, HasFactory;

    // Barcode types
    public const TYPE_EAN13 = 'ean13';
    public const TYPE_EAN8 = 'ean8';
    public const TYPE_UPC_A = 'upc_a';
    public const TYPE_UPC_E = 'upc_e';
    public const TYPE_CODE128 = 'code128';
    public const TYPE_CODE39 = 'code39';
    public const TYPE_QR = 'qr';
    public const TYPE_DATAMATRIX = 'datamatrix';
    public const TYPE_ITF14 = 'itf14';
    public const TYPE_ISBN = 'isbn';
    public const TYPE_ISSN = 'issn';
    public const TYPE_GS1_128 = 'gs1_128';
    public const TYPE_CUSTOM = 'custom';

    // Usage types
    public const USAGE_PRODUCT = 'product';
    public const USAGE_PACKAGING = 'packaging';
    public const USAGE_PALLET = 'pallet';
    public const USAGE_INTERNAL = 'internal';
    public const USAGE_SHELF = 'shelf';
    public const USAGE_PRICE_TAG = 'price_tag';

    protected $fillable = [
        'organization_id',
        'product_id',
        'variant_id',
        'batch_id',
        'barcode_value',
        'barcode_type',
        'barcode_image_path',
        'usage',
        'is_primary',
        'gtin',
        'gs1_company_prefix',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('barcode_type', $type);
    }

    public function scopeByUsage($query, string $usage)
    {
        return $query->where('usage', $usage);
    }

    public function scopeByValue($query, string $value)
    {
        return $query->where('barcode_value', $value);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByGtin($query, string $gtin)
    {
        return $query->where('gtin', $gtin);
    }

    // Helpers
    public function isPrimary(): bool
    {
        return $this->is_primary;
    }

    /**
     * Validate barcode format.
     */
    public static function validateBarcode(string $barcode, string $type): bool
    {
        return match ($type) {
            self::TYPE_EAN13 => self::validateEan13($barcode),
            self::TYPE_EAN8 => self::validateEan8($barcode),
            self::TYPE_UPC_A => self::validateUpcA($barcode),
            default => strlen($barcode) > 0,
        };
    }

    protected static function validateEan13(string $barcode): bool
    {
        if (!preg_match('/^\d{13}$/', $barcode)) {
            return false;
        }

        return self::calculateEanCheckDigit(substr($barcode, 0, 12)) === (int) $barcode[12];
    }

    protected static function validateEan8(string $barcode): bool
    {
        if (!preg_match('/^\d{8}$/', $barcode)) {
            return false;
        }

        return self::calculateEan8CheckDigit(substr($barcode, 0, 7)) === (int) $barcode[7];
    }

    protected static function validateUpcA(string $barcode): bool
    {
        if (!preg_match('/^\d{12}$/', $barcode)) {
            return false;
        }

        return self::calculateUpcCheckDigit(substr($barcode, 0, 11)) === (int) $barcode[11];
    }

    /**
     * Calculate EAN-13 check digit.
     */
    public static function calculateEanCheckDigit(string $digits): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $digits[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Calculate EAN-8 check digit.
     */
    public static function calculateEan8CheckDigit(string $digits): int
    {
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $digits[$i] * ($i % 2 === 0 ? 3 : 1);
        }
        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Calculate UPC-A check digit.
     */
    public static function calculateUpcCheckDigit(string $digits): int
    {
        $odd = 0;
        $even = 0;
        for ($i = 0; $i < 11; $i++) {
            if ($i % 2 === 0) {
                $odd += (int) $digits[$i];
            } else {
                $even += (int) $digits[$i];
            }
        }
        return (10 - (($odd * 3 + $even) % 10)) % 10;
    }

    /**
     * Generate internal barcode.
     */
    public static function generateInternalBarcode(int $organizationId, int $productId): string
    {
        $prefix = '20'; // Internal barcode prefix
        $orgPart = str_pad((string) ($organizationId % 10000), 4, '0', STR_PAD_LEFT);
        $productPart = str_pad((string) ($productId % 100000), 5, '0', STR_PAD_LEFT);
        $digits = $prefix . $orgPart . $productPart;
        $checkDigit = self::calculateEanCheckDigit($digits);

        return $digits . $checkDigit;
    }

    /**
     * Find product by barcode value.
     */
    public static function findByBarcodeValue(string $barcodeValue): ?self
    {
        return static::where('barcode_value', $barcodeValue)
            ->where('is_active', true)
            ->with(['product', 'variant'])
            ->first();
    }
}
