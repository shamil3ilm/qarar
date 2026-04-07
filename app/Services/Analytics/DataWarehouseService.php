<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Analytics\DimCustomer;
use App\Models\Analytics\DimOrganization;
use App\Models\Analytics\DimProduct;
use App\Models\Analytics\DimTime;
use App\Models\Analytics\DimVendor;
use App\Models\Analytics\DimWarehouse;
use App\Models\Analytics\FactInventoryMovement;
use App\Models\Analytics\FactPurchase;
use App\Models\Analytics\FactSale;
use App\Models\Core\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DataWarehouseService
{
    // ==========================================
    // Dimension Sync
    // ==========================================

    public function syncDimensions(int $organizationId): array
    {
        return [
            'products'   => $this->syncProducts($organizationId),
            'customers'  => $this->syncCustomers($organizationId),
            'vendors'    => $this->syncVendors($organizationId),
            'warehouses' => $this->syncWarehouses($organizationId),
        ];
    }

    private function syncProducts(int $organizationId): int
    {
        $products = DB::table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin('units_of_measure', 'products.unit_of_measure_id', '=', 'units_of_measure.id')
            ->where('products.organization_id', $organizationId)
            ->select([
                'products.id as product_id',
                'products.product_code',
                'products.name as product_name',
                'categories.name as category_name',
                DB::raw('NULL as subcategory_name'),
                DB::raw("COALESCE(units_of_measure.symbol, 'EA') as unit_of_measure"),
                DB::raw("COALESCE(products.type, 'product') as product_type"),
                'products.is_active',
            ])
            ->get();

        $count = 0;
        $now = Carbon::now();

        foreach ($products as $product) {
            DimProduct::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'product_id'      => $product->product_id,
                ],
                [
                    'product_code'     => $product->product_code,
                    'product_name'     => $product->product_name,
                    'category_name'    => $product->category_name,
                    'subcategory_name' => $product->subcategory_name,
                    'unit_of_measure'  => $product->unit_of_measure,
                    'product_type'     => $product->product_type,
                    'is_active'        => (bool) $product->is_active,
                    'synced_at'        => $now,
                ]
            );
            $count++;
        }

        return $count;
    }

    private function syncCustomers(int $organizationId): int
    {
        $contacts = DB::table('contacts')
            ->where('organization_id', $organizationId)
            ->whereIn('type', ['customer', 'both'])
            ->select([
                'id as contact_id',
                DB::raw("COALESCE(customer_code, CONCAT('CUST-', id)) as customer_code"),
                'name as customer_name',
                'customer_group',
                'country_code',
                'city',
                DB::raw("COALESCE(currency_code, 'SAR') as currency_code"),
                'credit_limit',
                'is_active',
            ])
            ->get();

        $count = 0;
        $now = Carbon::now();

        foreach ($contacts as $contact) {
            DimCustomer::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'contact_id'      => $contact->contact_id,
                ],
                [
                    'customer_code'  => $contact->customer_code,
                    'customer_name'  => $contact->customer_name,
                    'customer_group' => $contact->customer_group,
                    'country_code'   => $contact->country_code,
                    'city'           => $contact->city,
                    'currency_code'  => $contact->currency_code,
                    'credit_limit'   => $contact->credit_limit,
                    'is_active'      => (bool) $contact->is_active,
                    'synced_at'      => $now,
                ]
            );
            $count++;
        }

        return $count;
    }

    private function syncVendors(int $organizationId): int
    {
        $contacts = DB::table('contacts')
            ->where('organization_id', $organizationId)
            ->whereIn('type', ['supplier', 'vendor', 'both'])
            ->select([
                'id as contact_id',
                DB::raw("COALESCE(vendor_code, CONCAT('VEND-', id)) as vendor_code"),
                'name as vendor_name',
                'vendor_group',
                'country_code',
                DB::raw("COALESCE(currency_code, 'SAR') as currency_code"),
                'payment_terms',
                'is_active',
            ])
            ->get();

        $count = 0;
        $now = Carbon::now();

        foreach ($contacts as $contact) {
            DimVendor::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'contact_id'      => $contact->contact_id,
                ],
                [
                    'vendor_code'   => $contact->vendor_code,
                    'vendor_name'   => $contact->vendor_name,
                    'vendor_group'  => $contact->vendor_group,
                    'country_code'  => $contact->country_code,
                    'currency_code' => $contact->currency_code,
                    'payment_terms' => $contact->payment_terms,
                    'is_active'     => (bool) $contact->is_active,
                    'synced_at'     => $now,
                ]
            );
            $count++;
        }

        return $count;
    }

    private function syncWarehouses(int $organizationId): int
    {
        $warehouses = DB::table('warehouses')
            ->where('organization_id', $organizationId)
            ->select([
                'id as warehouse_id',
                'code as warehouse_code',
                'name as warehouse_name',
                'location',
                'is_active',
            ])
            ->get();

        $count = 0;

        foreach ($warehouses as $wh) {
            DimWarehouse::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'warehouse_id'    => $wh->warehouse_id,
                ],
                [
                    'warehouse_code' => $wh->warehouse_code,
                    'warehouse_name' => $wh->warehouse_name,
                    'location'       => $wh->location,
                    'is_active'      => (bool) $wh->is_active,
                ]
            );
            $count++;
        }

        return $count;
    }

    // ==========================================
    // Dim Time Population
    // ==========================================

    public function populateDimTime(int $yearFrom, int $yearTo): int
    {
        $count = 0;
        $current = Carbon::create($yearFrom, 1, 1);
        $end = Carbon::create($yearTo, 12, 31);

        while ($current->lte($end)) {
            $date = $current->toDateString();

            DimTime::firstOrCreate(
                ['full_date' => $date],
                [
                    'day_of_week'  => (int) $current->dayOfWeek,
                    'day_name'     => $current->format('l'),
                    'day_of_month' => (int) $current->day,
                    'week_of_year' => (int) $current->weekOfYear,
                    'month_number' => (int) $current->month,
                    'month_name'   => $current->format('F'),
                    'quarter'      => (int) $current->quarter,
                    'year'         => (int) $current->year,
                    'fiscal_year'  => (int) $current->year,
                    'fiscal_period' => (int) $current->month,
                    'is_weekend'   => $current->isWeekend(),
                    'is_holiday'   => false,
                ]
            );

            $count++;
            $current->addDay();
        }

        return $count;
    }

    // ==========================================
    // Fact Table Loading
    // ==========================================

    public function loadFactSales(int $organizationId, string $dateFrom, string $dateTo): int
    {
        $lines = DB::table('invoice_lines')
            ->join('invoices', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->where('invoices.organization_id', $organizationId)
            ->whereBetween(DB::raw('DATE(invoices.invoice_date)'), [$dateFrom, $dateTo])
            ->whereNull('invoices.deleted_at')
            ->whereNull('invoice_lines.deleted_at')
            ->select([
                'invoices.id as invoice_id',
                'invoice_lines.id as invoice_line_id',
                'invoices.contact_id',
                'invoice_lines.product_id',
                DB::raw('DATE(invoices.invoice_date) as invoice_date'),
                'invoice_lines.quantity',
                'invoice_lines.unit_price',
                'invoice_lines.subtotal as net_amount',
                DB::raw('COALESCE(invoice_lines.tax_amount, 0) as tax_amount'),
                'invoice_lines.total as gross_amount',
                DB::raw('COALESCE(invoice_lines.discount_amount, 0) as discount_amount'),
                DB::raw('COALESCE(invoice_lines.cost_amount, 0) as cost_amount'),
                DB::raw("COALESCE(invoices.currency_code, 'SAR') as currency_code"),
            ])
            ->get();

        $count = 0;

        foreach ($lines as $line) {
            $dimProduct = DimProduct::where('organization_id', $organizationId)
                ->where('product_id', $line->product_id)
                ->first();

            $dimCustomer = DimCustomer::where('organization_id', $organizationId)
                ->where('contact_id', $line->contact_id)
                ->first();

            $dimTime = DimTime::where('full_date', $line->invoice_date)->first();

            if (!$dimProduct || !$dimCustomer || !$dimTime) {
                continue;
            }

            $netAmount = (float) $line->net_amount;
            $costAmount = (float) $line->cost_amount;

            FactSale::updateOrCreate(
                [
                    'organization_id'  => $organizationId,
                    'invoice_line_id'  => $line->invoice_line_id,
                ],
                [
                    'dim_product_id'  => $dimProduct->id,
                    'dim_customer_id' => $dimCustomer->id,
                    'dim_time_id'     => $dimTime->id,
                    'invoice_id'      => $line->invoice_id,
                    'quantity'        => $line->quantity,
                    'unit_price'      => $line->unit_price,
                    'net_amount'      => $netAmount,
                    'tax_amount'      => $line->tax_amount,
                    'gross_amount'    => $line->gross_amount,
                    'discount_amount' => $line->discount_amount,
                    'cost_amount'     => $costAmount,
                    'gross_margin'    => $netAmount - $costAmount,
                    'currency_code'   => $line->currency_code,
                ]
            );
            $count++;
        }

        return $count;
    }

    public function loadFactPurchases(int $organizationId, string $dateFrom, string $dateTo): int
    {
        $lines = DB::table('bill_lines')
            ->join('bills', 'bill_lines.bill_id', '=', 'bills.id')
            ->where('bills.organization_id', $organizationId)
            ->whereBetween(DB::raw('DATE(bills.bill_date)'), [$dateFrom, $dateTo])
            ->whereNull('bills.deleted_at')
            ->whereNull('bill_lines.deleted_at')
            ->select([
                'bills.id as bill_id',
                'bill_lines.id as bill_line_id',
                'bills.contact_id',
                'bill_lines.product_id',
                DB::raw('DATE(bills.bill_date) as bill_date'),
                'bill_lines.quantity',
                'bill_lines.unit_price',
                'bill_lines.subtotal as net_amount',
                DB::raw('COALESCE(bill_lines.tax_amount, 0) as tax_amount'),
                'bill_lines.total as gross_amount',
                DB::raw("COALESCE(bills.currency_code, 'SAR') as currency_code"),
            ])
            ->get();

        $count = 0;

        foreach ($lines as $line) {
            $dimProduct = DimProduct::where('organization_id', $organizationId)
                ->where('product_id', $line->product_id)
                ->first();

            $dimVendor = DimVendor::where('organization_id', $organizationId)
                ->where('contact_id', $line->contact_id)
                ->first();

            $dimTime = DimTime::where('full_date', $line->bill_date)->first();

            if (!$dimProduct || !$dimVendor || !$dimTime) {
                continue;
            }

            FactPurchase::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'bill_id'         => $line->bill_id,
                    'dim_product_id'  => $dimProduct->id,
                ],
                [
                    'dim_vendor_id' => $dimVendor->id,
                    'dim_time_id'   => $dimTime->id,
                    'quantity'      => $line->quantity,
                    'unit_price'    => $line->unit_price,
                    'net_amount'    => $line->net_amount,
                    'tax_amount'    => $line->tax_amount,
                    'gross_amount'  => $line->gross_amount,
                    'currency_code' => $line->currency_code,
                ]
            );
            $count++;
        }

        return $count;
    }

    public function loadFactInventory(int $organizationId, string $dateFrom, string $dateTo): int
    {
        $movements = DB::table('stock_movements')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->where('stock_movements.organization_id', $organizationId)
            ->whereBetween(DB::raw('DATE(stock_movements.created_at)'), [$dateFrom, $dateTo])
            ->whereNull('stock_movements.deleted_at')
            ->select([
                'stock_movements.id',
                'stock_movements.product_id',
                'stock_movements.warehouse_id',
                DB::raw('DATE(stock_movements.created_at) as movement_date'),
                'stock_movements.movement_type',
                DB::raw('CASE WHEN stock_movements.quantity > 0 THEN stock_movements.quantity ELSE 0 END as quantity_in'),
                DB::raw('CASE WHEN stock_movements.quantity < 0 THEN ABS(stock_movements.quantity) ELSE 0 END as quantity_out'),
                'stock_movements.quantity as quantity_balance',
                DB::raw('COALESCE(stock_movements.unit_cost, 0) as unit_cost'),
                DB::raw('COALESCE(stock_movements.total_cost, 0) as total_cost'),
                DB::raw("COALESCE(stock_movements.currency_code, 'SAR') as currency_code"),
                'stock_movements.reference_type',
            ])
            ->get();

        $count = 0;

        foreach ($movements as $movement) {
            $dimProduct = DimProduct::where('organization_id', $organizationId)
                ->where('product_id', $movement->product_id)
                ->first();

            $dimWarehouse = DimWarehouse::where('organization_id', $organizationId)
                ->where('warehouse_id', $movement->warehouse_id)
                ->first();

            $dimTime = DimTime::where('full_date', $movement->movement_date)->first();

            if (!$dimProduct || !$dimWarehouse || !$dimTime) {
                continue;
            }

            FactInventoryMovement::create([
                'organization_id'  => $organizationId,
                'dim_product_id'   => $dimProduct->id,
                'dim_warehouse_id' => $dimWarehouse->id,
                'dim_time_id'      => $dimTime->id,
                'movement_type'    => $movement->movement_type,
                'quantity_in'      => $movement->quantity_in,
                'quantity_out'     => $movement->quantity_out,
                'quantity_balance' => $movement->quantity_balance,
                'unit_cost'        => $movement->unit_cost,
                'total_cost'       => $movement->total_cost,
                'currency_code'    => $movement->currency_code,
                'reference_type'   => $movement->reference_type,
            ]);
            $count++;
        }

        return $count;
    }

    // ==========================================
    // OLAP Queries
    // ==========================================

    public function getSalesCube(int $organizationId, array $filters, array $dimensions): array
    {
        $query = DB::table('fact_sales as fs')
            ->join('dim_time as dt', 'fs.dim_time_id', '=', 'dt.id')
            ->join('dim_product as dp', 'fs.dim_product_id', '=', 'dp.id')
            ->join('dim_customer as dc', 'fs.dim_customer_id', '=', 'dc.id')
            ->where('fs.organization_id', $organizationId);

        $this->applyDateFilters($query, 'dt', $filters);

        if (!empty($filters['product_id'])) {
            $query->where('dp.product_id', $filters['product_id']);
        }
        if (!empty($filters['customer_id'])) {
            $query->where('dc.contact_id', $filters['customer_id']);
        }
        if (!empty($filters['category_name'])) {
            $query->where('dp.category_name', $filters['category_name']);
        }

        $selectColumns = [
            DB::raw('SUM(fs.net_amount) as total_net'),
            DB::raw('SUM(fs.gross_amount) as total_gross'),
            DB::raw('SUM(fs.tax_amount) as total_tax'),
            DB::raw('SUM(fs.quantity) as total_quantity'),
            DB::raw('SUM(fs.gross_margin) as total_margin'),
            DB::raw('COUNT(DISTINCT fs.invoice_id) as invoice_count'),
        ];

        $groupByColumns = [];

        if (in_array('month', $dimensions, true)) {
            $selectColumns[] = DB::raw('dt.year');
            $selectColumns[] = DB::raw('dt.month_number');
            $selectColumns[] = DB::raw('dt.month_name');
            $groupByColumns[] = 'dt.year';
            $groupByColumns[] = 'dt.month_number';
            $groupByColumns[] = 'dt.month_name';
        }
        if (in_array('product', $dimensions, true)) {
            $selectColumns[] = DB::raw('dp.product_name');
            $selectColumns[] = DB::raw('dp.category_name');
            $groupByColumns[] = 'dp.id';
            $groupByColumns[] = 'dp.product_name';
            $groupByColumns[] = 'dp.category_name';
        }
        if (in_array('customer', $dimensions, true)) {
            $selectColumns[] = DB::raw('dc.customer_name');
            $groupByColumns[] = 'dc.id';
            $groupByColumns[] = 'dc.customer_name';
        }

        $query->select($selectColumns);

        if (!empty($groupByColumns)) {
            $query->groupBy($groupByColumns);
        }

        return $query->get()->toArray();
    }

    public function getPurchaseCube(int $organizationId, array $filters): array
    {
        $query = DB::table('fact_purchases as fp')
            ->join('dim_time as dt', 'fp.dim_time_id', '=', 'dt.id')
            ->join('dim_product as dp', 'fp.dim_product_id', '=', 'dp.id')
            ->join('dim_vendor as dv', 'fp.dim_vendor_id', '=', 'dv.id')
            ->where('fp.organization_id', $organizationId)
            ->select([
                DB::raw('dt.year'),
                DB::raw('dt.month_number'),
                DB::raw('dt.month_name'),
                DB::raw('dv.vendor_name'),
                DB::raw('dp.product_name'),
                DB::raw('dp.category_name'),
                DB::raw('SUM(fp.net_amount) as total_net'),
                DB::raw('SUM(fp.gross_amount) as total_gross'),
                DB::raw('SUM(fp.tax_amount) as total_tax'),
                DB::raw('SUM(fp.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT fp.bill_id) as bill_count'),
            ]);

        $this->applyDateFilters($query, 'dt', $filters);

        if (!empty($filters['vendor_id'])) {
            $query->where('dv.contact_id', $filters['vendor_id']);
        }

        return $query->groupBy(
            'dt.year', 'dt.month_number', 'dt.month_name',
            'dv.id', 'dv.vendor_name',
            'dp.id', 'dp.product_name', 'dp.category_name'
        )->get()->toArray();
    }

    public function getInventoryCube(int $organizationId, array $filters): array
    {
        $query = DB::table('fact_inventory_movements as fim')
            ->join('dim_time as dt', 'fim.dim_time_id', '=', 'dt.id')
            ->join('dim_product as dp', 'fim.dim_product_id', '=', 'dp.id')
            ->join('dim_warehouse as dw', 'fim.dim_warehouse_id', '=', 'dw.id')
            ->where('fim.organization_id', $organizationId)
            ->select([
                DB::raw('dt.year'),
                DB::raw('dt.month_number'),
                DB::raw('dt.month_name'),
                DB::raw('dp.product_name'),
                DB::raw('dp.category_name'),
                DB::raw('dw.warehouse_name'),
                DB::raw('fim.movement_type'),
                DB::raw('SUM(fim.quantity_in) as total_in'),
                DB::raw('SUM(fim.quantity_out) as total_out'),
                DB::raw('SUM(fim.total_cost) as total_cost'),
                DB::raw('COUNT(*) as movement_count'),
            ]);

        $this->applyDateFilters($query, 'dt', $filters);

        if (!empty($filters['warehouse_id'])) {
            $query->where('dw.warehouse_id', $filters['warehouse_id']);
        }
        if (!empty($filters['movement_type'])) {
            $query->where('fim.movement_type', $filters['movement_type']);
        }

        return $query->groupBy(
            'dt.year', 'dt.month_number', 'dt.month_name',
            'dp.id', 'dp.product_name', 'dp.category_name',
            'dw.id', 'dw.warehouse_name',
            'fim.movement_type'
        )->get()->toArray();
    }

    // ==========================================
    // Aggregated Queries
    // ==========================================

    public function getTopProducts(int $organizationId, int $limit, array $filters): array
    {
        $query = DB::table('fact_sales as fs')
            ->join('dim_product as dp', 'fs.dim_product_id', '=', 'dp.id')
            ->join('dim_time as dt', 'fs.dim_time_id', '=', 'dt.id')
            ->where('fs.organization_id', $organizationId)
            ->select([
                'dp.product_id',
                'dp.product_code',
                'dp.product_name',
                'dp.category_name',
                DB::raw('SUM(fs.net_amount) as total_revenue'),
                DB::raw('SUM(fs.gross_margin) as total_margin'),
                DB::raw('SUM(fs.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT fs.invoice_id) as order_count'),
            ]);

        $this->applyDateFilters($query, 'dt', $filters);

        return $query
            ->groupBy('dp.id', 'dp.product_id', 'dp.product_code', 'dp.product_name', 'dp.category_name')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getTopCustomers(int $organizationId, int $limit, array $filters): array
    {
        $query = DB::table('fact_sales as fs')
            ->join('dim_customer as dc', 'fs.dim_customer_id', '=', 'dc.id')
            ->join('dim_time as dt', 'fs.dim_time_id', '=', 'dt.id')
            ->where('fs.organization_id', $organizationId)
            ->select([
                'dc.contact_id',
                'dc.customer_code',
                'dc.customer_name',
                'dc.customer_group',
                DB::raw('SUM(fs.net_amount) as total_revenue'),
                DB::raw('SUM(fs.gross_margin) as total_margin'),
                DB::raw('COUNT(DISTINCT fs.invoice_id) as invoice_count'),
            ]);

        $this->applyDateFilters($query, 'dt', $filters);

        return $query
            ->groupBy('dc.id', 'dc.contact_id', 'dc.customer_code', 'dc.customer_name', 'dc.customer_group')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getSalesTrend(int $organizationId, array $filters, string $groupBy): array
    {
        $query = DB::table('fact_sales as fs')
            ->join('dim_time as dt', 'fs.dim_time_id', '=', 'dt.id')
            ->where('fs.organization_id', $organizationId);

        $this->applyDateFilters($query, 'dt', $filters);

        $selectColumns = [
            DB::raw('SUM(fs.net_amount) as total_net'),
            DB::raw('SUM(fs.gross_amount) as total_gross'),
            DB::raw('SUM(fs.quantity) as total_quantity'),
            DB::raw('COUNT(DISTINCT fs.invoice_id) as invoice_count'),
        ];

        $groupByColumns = [];

        if ($groupBy === 'week') {
            $selectColumns[] = DB::raw('dt.year');
            $selectColumns[] = DB::raw('dt.week_of_year');
            $groupByColumns = ['dt.year', 'dt.week_of_year'];
            $orderBy = ['dt.year', 'dt.week_of_year'];
        } elseif ($groupBy === 'month') {
            $selectColumns[] = DB::raw('dt.year');
            $selectColumns[] = DB::raw('dt.month_number');
            $selectColumns[] = DB::raw('dt.month_name');
            $groupByColumns = ['dt.year', 'dt.month_number', 'dt.month_name'];
            $orderBy = ['dt.year', 'dt.month_number'];
        } else {
            $selectColumns[] = DB::raw('dt.full_date');
            $groupByColumns = ['dt.full_date'];
            $orderBy = ['dt.full_date'];
        }

        $query->select($selectColumns)->groupBy($groupByColumns);

        foreach ($orderBy as $col) {
            $query->orderBy($col);
        }

        return $query->get()->toArray();
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function applyDateFilters(mixed $query, string $timeAlias, array $filters): void
    {
        if (!empty($filters['date_from'])) {
            $query->where("{$timeAlias}.full_date", '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where("{$timeAlias}.full_date", '<=', $filters['date_to']);
        }
        if (!empty($filters['year'])) {
            $query->where("{$timeAlias}.year", $filters['year']);
        }
        if (!empty($filters['month'])) {
            $query->where("{$timeAlias}.month_number", $filters['month']);
        }
        if (!empty($filters['quarter'])) {
            $query->where("{$timeAlias}.quarter", $filters['quarter']);
        }
    }
}
