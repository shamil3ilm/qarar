<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\PriceCheckLog;
use App\Models\Inventory\PriceCheckStation;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductBarcode;
use Illuminate\Support\Facades\DB;

class PriceCheckService
{
    public function __construct(
        private BarcodeService $barcodeService
    ) {}

    /**
     * Check price by scanning a barcode/QR/SKU.
     */
    public function checkPrice(string $scanValue, string $scanType, int $branchId, ?int $stationId = null, ?int $contactId = null): array
    {
        $result = $this->barcodeService->lookup($scanValue);

        // If not found via barcode, try SKU lookup
        if (!$result && in_array($scanType, [PriceCheckLog::SCAN_MANUAL, PriceCheckLog::SCAN_SKU])) {
            $product = Product::where('sku', $scanValue)
                ->where('organization_id', auth()->user()->organization_id)
                ->first();
            if ($product) {
                $result = [
                    'product' => $product,
                    'variant' => null,
                    'barcode' => null,
                    'source' => 'sku_lookup',
                ];
            }
        }

        // Build log data
        $logData = [
            'organization_id' => auth()->user()->organization_id,
            'station_id' => $stationId,
            'branch_id' => $branchId,
            'scan_type' => $scanType,
            'scan_value' => $scanValue,
            'contact_id' => $contactId,
            'scanned_at' => now(),
        ];

        if (!$result) {
            // Product not found
            $logData['scan_successful'] = false;
            $logData['error_type'] = PriceCheckLog::ERROR_NOT_FOUND;
            $logData['error_message'] = 'Product not found for scanned value.';

            $this->logScan($logData);

            return [
                'found' => false,
                'error' => 'Product not found.',
                'error_type' => PriceCheckLog::ERROR_NOT_FOUND,
            ];
        }

        $product = $result['product'];

        if (!$product->is_active) {
            $logData['scan_successful'] = false;
            $logData['product_id'] = $product->id;
            $logData['product_name'] = $product->name;
            $logData['product_sku'] = $product->sku;
            $logData['error_type'] = PriceCheckLog::ERROR_INACTIVE;
            $logData['error_message'] = 'Product is inactive.';

            $this->logScan($logData);

            return [
                'found' => false,
                'error' => 'Product is inactive.',
                'error_type' => PriceCheckLog::ERROR_INACTIVE,
            ];
        }

        // Get pricing info
        $displayedPrice = (float) $product->selling_price;
        $originalPrice = $displayedPrice;
        $hasPromotion = false;
        $promotionName = null;
        $promotionDiscount = null;

        // Get stock info
        $stockAvailable = $product->getTotalStock();
        $stockStatus = match (true) {
            $stockAvailable <= 0 => PriceCheckLog::STOCK_OUT_OF_STOCK,
            $product->reorder_level && $stockAvailable <= $product->reorder_level => PriceCheckLog::STOCK_LOW_STOCK,
            default => PriceCheckLog::STOCK_IN_STOCK,
        };

        // Log successful scan
        $logData['scan_successful'] = true;
        $logData['product_id'] = $product->id;
        $logData['variant_id'] = $result['variant']?->id;
        $logData['product_name'] = $product->name;
        $logData['product_sku'] = $product->sku;
        $logData['displayed_price'] = $displayedPrice;
        $logData['original_price'] = $originalPrice;
        $logData['currency_code'] = $product->organization->base_currency ?? 'SAR';
        $logData['has_promotion'] = $hasPromotion;
        $logData['promotion_name'] = $promotionName;
        $logData['promotion_discount'] = $promotionDiscount;
        $logData['stock_available'] = $stockAvailable;
        $logData['stock_status'] = $stockStatus;

        $this->logScan($logData);

        return [
            'found' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'description' => $product->description,
                'image_url' => $product->image_url,
            ],
            'variant' => $result['variant'],
            'pricing' => [
                'price' => $displayedPrice,
                'original_price' => $originalPrice,
                'has_promotion' => $hasPromotion,
                'promotion_name' => $promotionName,
                'promotion_discount' => $promotionDiscount,
                'currency_code' => $logData['currency_code'],
            ],
            'stock' => [
                'available' => $stockAvailable,
                'status' => $stockStatus,
            ],
        ];
    }

    /**
     * Log a price check scan.
     */
    public function logScan(array $data): PriceCheckLog
    {
        return PriceCheckLog::create($data);
    }

    /**
     * Get station statistics.
     */
    public function getStationStats(PriceCheckStation $station): array
    {
        $today = now()->startOfDay();
        $logsQuery = PriceCheckLog::where('station_id', $station->id);

        return [
            'station' => [
                'id' => $station->uuid,
                'name' => $station->name,
                'status' => $station->status,
                'is_online' => $station->isOnline(),
                'last_heartbeat' => $station->last_heartbeat_at?->toISOString(),
            ],
            'today' => [
                'total_scans' => (clone $logsQuery)->where('scanned_at', '>=', $today)->count(),
                'successful_scans' => (clone $logsQuery)->where('scanned_at', '>=', $today)->successful()->count(),
                'failed_scans' => (clone $logsQuery)->where('scanned_at', '>=', $today)->failed()->count(),
            ],
            'last_7_days' => [
                'total_scans' => (clone $logsQuery)->where('scanned_at', '>=', now()->subDays(7))->count(),
                'successful_scans' => (clone $logsQuery)->where('scanned_at', '>=', now()->subDays(7))->successful()->count(),
                'failed_scans' => (clone $logsQuery)->where('scanned_at', '>=', now()->subDays(7))->failed()->count(),
            ],
            'error_breakdown' => PriceCheckLog::where('station_id', $station->id)
                ->where('scanned_at', '>=', now()->subDays(7))
                ->whereNotNull('error_type')
                ->selectRaw('error_type, COUNT(*) as count')
                ->groupBy('error_type')
                ->get()
                ->keyBy('error_type'),
        ];
    }

    /**
     * Get scan analytics for a branch or organization.
     */
    public function getScanAnalytics(?int $branchId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = PriceCheckLog::query();

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($fromDate) {
            $query->where('scanned_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('scanned_at', '<=', $toDate);
        }

        $totalScans = (clone $query)->count();
        $successfulScans = (clone $query)->where('scan_successful', true)->count();
        $failedScans = (clone $query)->where('scan_successful', false)->count();

        return [
            'total_scans' => $totalScans,
            'successful_scans' => $successfulScans,
            'failed_scans' => $failedScans,
            'success_rate' => $totalScans > 0 ? round(($successfulScans / $totalScans) * 100, 2) : 0,
            'top_scanned_products' => (clone $query)
                ->where('scan_successful', true)
                ->whereNotNull('product_id')
                ->selectRaw('product_id, product_name, COUNT(*) as scan_count')
                ->groupBy('product_id', 'product_name')
                ->orderByDesc('scan_count')
                ->limit(10)
                ->get(),
            'scans_by_type' => (clone $query)
                ->selectRaw('scan_type, COUNT(*) as count')
                ->groupBy('scan_type')
                ->get()
                ->keyBy('scan_type'),
            'errors_by_type' => (clone $query)
                ->whereNotNull('error_type')
                ->selectRaw('error_type, COUNT(*) as count')
                ->groupBy('error_type')
                ->get()
                ->keyBy('error_type'),
            'hourly_distribution' => (clone $query)
                ->selectRaw('HOUR(scanned_at) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get(),
        ];
    }
}
