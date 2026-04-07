<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductBarcode;
use Illuminate\Support\Facades\DB;

class BarcodeService
{
    public function __construct() {}

    /**
     * Generate a barcode for a product.
     */
    public function generate(int $productId, string $barcodeType = 'ean13', string $usage = 'product'): ProductBarcode
    {
        return DB::transaction(function () use ($productId, $barcodeType, $usage) {
            $product = Product::findOrFail($productId);
            $organizationId = $product->organization_id;

            // Generate barcode value based on type
            $barcodeValue = $this->generateBarcodeValue($organizationId, $productId, $barcodeType);

            // Check if primary should be set
            $isPrimary = !ProductBarcode::where('product_id', $productId)->where('is_primary', true)->exists();

            return ProductBarcode::create([
                'organization_id' => $organizationId,
                'product_id' => $productId,
                'barcode_value' => $barcodeValue,
                'barcode_type' => $barcodeType,
                'usage' => $usage,
                'is_primary' => $isPrimary,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Lookup a product by barcode value.
     */
    public function lookup(string $barcodeValue): ?array
    {
        $organizationId = auth()->user()?->organization_id;

        $barcode = ProductBarcode::where('barcode_value', $barcodeValue)
            ->where('is_active', true)
            ->when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
            ->with(['product', 'variant'])
            ->first();

        if (!$barcode) {
            // Also check the products table barcode field
            $product = Product::where('barcode', $barcodeValue)
                ->when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
                ->first();
            if ($product) {
                return [
                    'product' => $product,
                    'variant' => null,
                    'barcode' => null,
                    'source' => 'product_table',
                ];
            }

            return null;
        }

        return [
            'product' => $barcode->product,
            'variant' => $barcode->variant,
            'barcode' => $barcode,
            'source' => 'barcode_table',
        ];
    }

    /**
     * Assign a barcode to a product.
     */
    public function assignToProduct(int $productId, string $barcodeValue, string $barcodeType, array $extra = []): ProductBarcode
    {
        return DB::transaction(function () use ($productId, $barcodeValue, $barcodeType, $extra) {
            $product = Product::findOrFail($productId);

            // Validate barcode format
            if (!ProductBarcode::validateBarcode($barcodeValue, $barcodeType)) {
                throw new \InvalidArgumentException("Invalid barcode format for type: {$barcodeType}");
            }

            $isPrimary = $extra['is_primary'] ?? false;

            // If setting as primary, remove primary from others
            if ($isPrimary) {
                ProductBarcode::where('product_id', $productId)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            return ProductBarcode::create(array_merge([
                'organization_id' => $product->organization_id,
                'product_id' => $productId,
                'variant_id' => $extra['variant_id'] ?? null,
                'batch_id' => $extra['batch_id'] ?? null,
                'barcode_value' => $barcodeValue,
                'barcode_type' => $barcodeType,
                'usage' => $extra['usage'] ?? ProductBarcode::USAGE_PRODUCT,
                'is_primary' => $isPrimary,
                'gtin' => $extra['gtin'] ?? null,
                'gs1_company_prefix' => $extra['gs1_company_prefix'] ?? null,
                'is_active' => true,
            ]));
        });
    }

    /**
     * Bulk generate barcodes for multiple products.
     */
    public function bulkGenerate(array $productIds, string $barcodeType = 'ean13', string $usage = 'product'): array
    {
        $results = [];

        foreach ($productIds as $productId) {
            try {
                $results[] = [
                    'product_id' => $productId,
                    'barcode' => $this->generate($productId, $barcodeType, $usage),
                    'success' => true,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'product_id' => $productId,
                    'error' => $e->getMessage(),
                    'success' => false,
                ];
            }
        }

        return $results;
    }

    /**
     * Print labels for barcodes.
     */
    public function printLabels(array $barcodeIds, string $format = 'standard'): array
    {
        $barcodes = ProductBarcode::with(['product', 'variant'])
            ->whereIn('id', $barcodeIds)
            ->get();

        $labels = [];
        foreach ($barcodes as $barcode) {
            $labels[] = [
                'barcode_id' => $barcode->id,
                'barcode_value' => $barcode->barcode_value,
                'barcode_type' => $barcode->barcode_type,
                'product_name' => $barcode->product->name,
                'sku' => $barcode->product->sku,
                'price' => $barcode->product->selling_price,
                'format' => $format,
            ];
        }

        return [
            'labels' => $labels,
            'count' => count($labels),
            'format' => $format,
        ];
    }

    /**
     * Generate barcode value based on type.
     */
    protected function generateBarcodeValue(int $organizationId, int $productId, string $barcodeType): string
    {
        return match ($barcodeType) {
            'ean13' => ProductBarcode::generateInternalBarcode($organizationId, $productId),
            'code128', 'code39' => sprintf('INT-%d-%06d', $organizationId, $productId),
            default => ProductBarcode::generateInternalBarcode($organizationId, $productId),
        };
    }
}
