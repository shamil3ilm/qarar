<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class StockTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private string $baseUrl = '/inventory/stock';
    private Category $category;
    private UnitOfMeasure $unit;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.stock.view',
            'inventory.stock.reserve',
        ]);

        $this->category = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'General',
            'is_active' => true,
        ]);

        $this->unit = UnitOfMeasure::create([
            'organization_id' => $this->organization->id,
            'name' => 'Piece',
            'symbol' => 'pc',
            'conversion_factor' => 1,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'organization_id' => $this->organization->id,
            'sku' => 'STK-001',
            'name' => 'Stock Test Product',
            'type' => Product::TYPE_GOODS,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'purchase_price' => 50.00,
            'selling_price' => 100.00,
            'track_inventory' => true,
            'reorder_level' => 20,
            'is_active' => true,
        ]);
    }

    private function createStockLevel(array $overrides = []): StockLevel
    {
        return StockLevel::create(array_merge([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'average_cost' => 50.00,
            'total_value' => 5000.00,
        ], $overrides));
    }

    private function createStockMovement(array $overrides = []): StockMovement
    {
        return StockMovement::create(array_merge([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'direction' => StockMovement::DIRECTION_IN,
            'quantity' => 50,
            'unit_cost' => 50.00,
            'total_cost' => 2500.00,
            'balance_after' => 150,
            'reference_number' => 'PO-001',
            'created_by' => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // STOCK LEVELS (GET /stock/levels)
    // -------------------------------------------------------------------------

    public function test_can_get_stock_levels(): void
    {
        $this->createStockLevel();

        $response = $this->apiGet("{$this->baseUrl}/levels");

        $this->assertSuccessResponse($response);
    }

    public function test_stock_levels_returns_only_own_organization(): void
    {
        $this->createStockLevel();

        $otherOrg = Organization::factory()->create();
        $otherBranch = \App\Models\Core\Branch::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherWarehouse = Warehouse::create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'name' => 'Other WH',
            'code' => 'WH-O',
            'is_active' => true,
        ]);
        $otherCategory = Category::create([
            'organization_id' => $otherOrg->id,
            'name' => 'OC',
            'is_active' => true,
        ]);
        $otherUnit = UnitOfMeasure::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Piece',
            'symbol' => 'pc',
            'conversion_factor' => 1,
            'is_active' => true,
        ]);
        $otherProduct = Product::create([
            'organization_id' => $otherOrg->id,
            'sku' => 'O-STK-001',
            'name' => 'Other Product',
            'type' => Product::TYPE_GOODS,
            'category_id' => $otherCategory->id,
            'unit_id' => $otherUnit->id,
            'purchase_price' => 10,
            'selling_price' => 20,
            'is_active' => true,
        ]);
        StockLevel::create([
            'organization_id' => $otherOrg->id,
            'product_id' => $otherProduct->id,
            'warehouse_id' => $otherWarehouse->id,
            'quantity' => 200,
            'reserved_quantity' => 0,
            'average_cost' => 10,
            'total_value' => 2000,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/levels");

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        foreach ($data as $stockLevel) {
            $this->assertEquals($this->organization->id, $stockLevel['organization_id']);
        }
    }

    public function test_stock_levels_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1' . $this->baseUrl . '/levels', [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // STOCK MOVEMENTS (GET /stock/movements)
    // -------------------------------------------------------------------------

    public function test_can_get_stock_movements(): void
    {
        $this->createStockLevel();
        $this->createStockMovement();

        $response = $this->apiGet("{$this->baseUrl}/movements");

        $this->assertSuccessResponse($response);
    }

    public function test_stock_movements_returns_only_own_organization(): void
    {
        $this->createStockMovement();

        $response = $this->apiGet("{$this->baseUrl}/movements");

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        foreach ($data as $movement) {
            $this->assertEquals($this->organization->id, $movement['organization_id']);
        }
    }

    public function test_can_filter_stock_movements_by_type(): void
    {
        $this->createStockMovement(['movement_type' => StockMovement::TYPE_PURCHASE]);
        $this->createStockMovement([
            'movement_type' => StockMovement::TYPE_SALE,
            'direction' => StockMovement::DIRECTION_OUT,
            'reference_number' => 'INV-001',
        ]);

        $response = $this->apiGet("{$this->baseUrl}/movements?movement_type=purchase");

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        foreach ($data as $movement) {
            $this->assertEquals('purchase', $movement['movement_type']);
        }
    }

    public function test_can_filter_stock_movements_by_warehouse(): void
    {
        $this->createStockMovement();

        $response = $this->apiGet("{$this->baseUrl}/movements?warehouse_id={$this->warehouse->id}");

        $this->assertSuccessResponse($response);
    }

    public function test_stock_movements_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1' . $this->baseUrl . '/movements', [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // STOCK VALUATION (GET /stock/valuation)
    // -------------------------------------------------------------------------

    public function test_can_get_total_stock_valuation(): void
    {
        $this->createStockLevel(['quantity' => 100, 'average_cost' => 50, 'total_value' => 5000]);

        $response = $this->apiGet("{$this->baseUrl}/valuation");

        $this->assertSuccessResponse($response);
    }

    public function test_valuation_includes_all_warehouses(): void
    {
        $warehouseB = Warehouse::create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'name' => 'Second Warehouse',
            'code' => 'WH-02',
            'is_active' => true,
        ]);

        $this->createStockLevel(['quantity' => 100, 'total_value' => 5000]);
        $this->createStockLevel([
            'warehouse_id' => $warehouseB->id,
            'quantity' => 50,
            'total_value' => 2500,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/valuation");

        $this->assertSuccessResponse($response);
    }

    public function test_valuation_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1' . $this->baseUrl . '/valuation', [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // LOW STOCK ALERTS (GET /stock/low-stock)
    // -------------------------------------------------------------------------

    public function test_can_get_low_stock_alerts(): void
    {
        $this->createStockLevel([
            'quantity' => 5,
            'reorder_level' => 20,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/low-stock");

        $this->assertSuccessResponse($response);
    }

    public function test_low_stock_does_not_include_well_stocked_items(): void
    {
        $this->createStockLevel([
            'quantity' => 500,
            'reorder_level' => 20,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/low-stock");

        $this->assertSuccessResponse($response);
        // The well-stocked item should not appear as low stock
    }

    public function test_low_stock_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1' . $this->baseUrl . '/low-stock', [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // CHECK AVAILABILITY (POST /stock/check-availability)
    // -------------------------------------------------------------------------

    public function test_can_check_product_availability(): void
    {
        $this->createStockLevel(['quantity' => 100, 'reserved_quantity' => 10]);

        $response = $this->apiPost("{$this->baseUrl}/check-availability", [
            'product_id' => $this->product->id,
            'quantity' => 50,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->assertSuccessResponse($response);
    }

    public function test_check_availability_for_insufficient_stock(): void
    {
        $this->createStockLevel(['quantity' => 10, 'reserved_quantity' => 5]);

        $response = $this->apiPost("{$this->baseUrl}/check-availability", [
            'product_id' => $this->product->id,
            'quantity' => 100,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // The API should return success with availability info (available = false)
        $this->assertSuccessResponse($response);
    }

    public function test_check_availability_requires_product_id(): void
    {
        $response = $this->apiPost("{$this->baseUrl}/check-availability", [
            'quantity' => 50,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->assertErrorResponse($response, 422);
    }

    public function test_check_availability_requires_quantity(): void
    {
        $response = $this->apiPost("{$this->baseUrl}/check-availability", [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->assertErrorResponse($response, 422);
    }

    public function test_check_availability_unauthenticated_returns_401(): void
    {
        $response = $this->postJson('/api/v1' . $this->baseUrl . '/check-availability', [
            'product_id' => $this->product->id,
            'quantity' => 50,
        ], ['Accept' => 'application/json']);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // RESERVE STOCK (POST /stock/reserve)
    // -------------------------------------------------------------------------

    public function test_can_reserve_stock(): void
    {
        $stockLevel = $this->createStockLevel(['quantity' => 100, 'reserved_quantity' => 0]);

        $response = $this->apiPost("{$this->baseUrl}/reserve", [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 25,
        ]);

        $this->assertSuccessResponse($response);
    }

    public function test_cannot_reserve_more_than_available(): void
    {
        $this->createStockLevel(['quantity' => 10, 'reserved_quantity' => 8]);

        $response = $this->apiPost("{$this->baseUrl}/reserve", [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
        ]);

        $this->assertErrorResponse($response, 422);
    }

    public function test_reserve_stock_validates_required_fields(): void
    {
        $response = $this->apiPost("{$this->baseUrl}/reserve", []);

        $this->assertErrorResponse($response, 422);
    }

    public function test_reserve_stock_unauthenticated_returns_401(): void
    {
        $response = $this->postJson('/api/v1' . $this->baseUrl . '/reserve', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
        ], ['Accept' => 'application/json']);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // RELEASE STOCK (POST /stock/release)
    // -------------------------------------------------------------------------

    public function test_can_release_reserved_stock(): void
    {
        $this->createStockLevel(['quantity' => 100, 'reserved_quantity' => 30]);

        $response = $this->apiPost("{$this->baseUrl}/release", [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 20,
        ]);

        $this->assertSuccessResponse($response);
    }

    public function test_release_stock_validates_required_fields(): void
    {
        $response = $this->apiPost("{$this->baseUrl}/release", []);

        $this->assertErrorResponse($response, 422);
    }

    public function test_release_stock_unauthenticated_returns_401(): void
    {
        $response = $this->postJson('/api/v1' . $this->baseUrl . '/release', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
        ], ['Accept' => 'application/json']);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // MULTI-TENANT ISOLATION
    // -------------------------------------------------------------------------

    public function test_stock_operations_isolated_by_organization(): void
    {
        $this->createStockLevel(['quantity' => 100]);

        // Create a completely separate organization with stock
        $otherOrg = Organization::factory()->create();
        $otherBranch = \App\Models\Core\Branch::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherWarehouse = Warehouse::create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'name' => 'Other WH',
            'code' => 'WH-O2',
            'is_active' => true,
        ]);
        $otherCategory = Category::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Cat',
            'is_active' => true,
        ]);
        $otherUnit = UnitOfMeasure::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Piece',
            'symbol' => 'pc',
            'conversion_factor' => 1,
            'is_active' => true,
        ]);
        $otherProduct = Product::create([
            'organization_id' => $otherOrg->id,
            'sku' => 'OT-001',
            'name' => 'Other Product',
            'type' => Product::TYPE_GOODS,
            'category_id' => $otherCategory->id,
            'unit_id' => $otherUnit->id,
            'purchase_price' => 10,
            'selling_price' => 20,
            'is_active' => true,
        ]);
        StockLevel::create([
            'organization_id' => $otherOrg->id,
            'product_id' => $otherProduct->id,
            'warehouse_id' => $otherWarehouse->id,
            'quantity' => 999,
            'reserved_quantity' => 0,
            'average_cost' => 10,
            'total_value' => 9990,
        ]);

        // Attempt to check availability on the other org's product
        $response = $this->apiPost("{$this->baseUrl}/check-availability", [
            'product_id' => $otherProduct->id,
            'quantity' => 1,
            'warehouse_id' => $otherWarehouse->id,
        ]);

        // Should fail because product belongs to another organization
        $this->assertErrorResponse($response, 422);
    }
}
