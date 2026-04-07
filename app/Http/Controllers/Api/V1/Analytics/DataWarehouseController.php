<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\DataWarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataWarehouseController extends Controller
{
    public function __construct(
        protected DataWarehouseService $service
    ) {}

    /**
     * POST /analytics/warehouse/sync
     * Sync all dimension tables for the authenticated organization.
     */
    public function sync(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $counts = $this->service->syncDimensions($organizationId);

        return $this->success($counts, 'Dimensions synced successfully');
    }

    /**
     * POST /analytics/warehouse/load-facts
     * Load fact tables for a given date range.
     */
    public function loadFacts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
            'tables'    => 'nullable|array',
            'tables.*'  => 'string|in:sales,purchases,inventory',
        ]);

        $organizationId = $this->organizationId($request);
        $dateFrom = $validated['date_from'];
        $dateTo   = $validated['date_to'];
        $tables   = $validated['tables'] ?? ['sales', 'purchases', 'inventory'];

        $results = [];

        if (in_array('sales', $tables, true)) {
            $results['sales'] = $this->service->loadFactSales($organizationId, $dateFrom, $dateTo);
        }
        if (in_array('purchases', $tables, true)) {
            $results['purchases'] = $this->service->loadFactPurchases($organizationId, $dateFrom, $dateTo);
        }
        if (in_array('inventory', $tables, true)) {
            $results['inventory'] = $this->service->loadFactInventory($organizationId, $dateFrom, $dateTo);
        }

        return $this->success($results, 'Fact tables loaded successfully');
    }

    /**
     * GET /analytics/warehouse/sales-cube
     * Flexible OLAP sales query.
     */
    public function salesCube(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'  => 'nullable|date',
            'date_to'    => 'nullable|date',
            'year'       => 'nullable|integer|min:2000|max:2100',
            'month'      => 'nullable|integer|min:1|max:12',
            'quarter'    => 'nullable|integer|min:1|max:4',
            'product_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            'category_name' => 'nullable|string',
            'dimensions' => 'nullable|array',
            'dimensions.*' => 'string|in:month,product,customer',
        ]);

        $organizationId = $this->organizationId($request);

        $filters = $request->only([
            'date_from', 'date_to', 'year', 'month', 'quarter',
            'product_id', 'customer_id', 'category_name',
        ]);

        $dimensions = $request->get('dimensions', ['month']);

        $data = $this->service->getSalesCube($organizationId, $filters, $dimensions);

        return $this->success($data);
    }

    /**
     * GET /analytics/warehouse/purchase-cube
     * OLAP purchase query.
     */
    public function purchaseCube(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'year'      => 'nullable|integer|min:2000|max:2100',
            'month'     => 'nullable|integer|min:1|max:12',
            'quarter'   => 'nullable|integer|min:1|max:4',
            'vendor_id' => 'nullable|integer',
        ]);

        $organizationId = $this->organizationId($request);
        $filters = $request->only(['date_from', 'date_to', 'year', 'month', 'quarter', 'vendor_id']);

        $data = $this->service->getPurchaseCube($organizationId, $filters);

        return $this->success($data);
    }

    /**
     * GET /analytics/warehouse/inventory-cube
     * OLAP inventory movement query.
     */
    public function inventoryCube(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'     => 'nullable|date',
            'date_to'       => 'nullable|date',
            'year'          => 'nullable|integer|min:2000|max:2100',
            'month'         => 'nullable|integer|min:1|max:12',
            'warehouse_id'  => 'nullable|integer',
            'movement_type' => 'nullable|string',
        ]);

        $organizationId = $this->organizationId($request);
        $filters = $request->only(['date_from', 'date_to', 'year', 'month', 'warehouse_id', 'movement_type']);

        $data = $this->service->getInventoryCube($organizationId, $filters);

        return $this->success($data);
    }

    /**
     * GET /analytics/warehouse/top-products
     * Top N products by revenue.
     */
    public function topProducts(Request $request): JsonResponse
    {
        $request->validate([
            'limit'     => 'nullable|integer|min:1|max:100',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'year'      => 'nullable|integer|min:2000|max:2100',
            'month'     => 'nullable|integer|min:1|max:12',
        ]);

        $organizationId = $this->organizationId($request);
        $limit   = (int) $request->get('limit', 10);
        $filters = $request->only(['date_from', 'date_to', 'year', 'month', 'quarter']);

        $data = $this->service->getTopProducts($organizationId, $limit, $filters);

        return $this->success($data);
    }

    /**
     * GET /analytics/warehouse/top-customers
     * Top N customers by revenue.
     */
    public function topCustomers(Request $request): JsonResponse
    {
        $request->validate([
            'limit'     => 'nullable|integer|min:1|max:100',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'year'      => 'nullable|integer|min:2000|max:2100',
            'month'     => 'nullable|integer|min:1|max:12',
        ]);

        $organizationId = $this->organizationId($request);
        $limit   = (int) $request->get('limit', 10);
        $filters = $request->only(['date_from', 'date_to', 'year', 'month', 'quarter']);

        $data = $this->service->getTopCustomers($organizationId, $limit, $filters);

        return $this->success($data);
    }

    /**
     * GET /analytics/warehouse/sales-trend
     * Monthly or weekly sales trend.
     */
    public function salesTrend(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'year'      => 'nullable|integer|min:2000|max:2100',
            'group_by'  => 'nullable|string|in:day,week,month',
        ]);

        $organizationId = $this->organizationId($request);
        $filters  = $request->only(['date_from', 'date_to', 'year', 'month', 'quarter']);
        $groupBy  = $request->get('group_by', 'month');

        $data = $this->service->getSalesTrend($organizationId, $filters, $groupBy);

        return $this->success($data);
    }
}
