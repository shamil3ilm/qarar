<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ProductTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private string $baseUrl = '/inventory/products';
    private Category $category;
    private UnitOfMeasure $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.products.view',
            'inventory.products.create',
            'inventory.products.edit',
            'inventory.products.delete',
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
    }

    private function validProductPayload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'PROD-001',
            'name' => 'Test Product',
            'type' => Product::TYPE_GOODS,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'purchase_price' => 50.00,
            'selling_price' => 100.00,
        ], $overrides);
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'organization_id' => $this->organization->id,
            'sku' => 'PROD-' . fake()->unique()->numerify('###'),
            'name' => fake()->words(3, true),
            'type' => Product::TYPE_GOODS,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'purchase_price' => 50.00,
            'selling_price' => 100.00,
            'is_active' => true,
            'track_inventory' => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // LIST (GET /products)
    // -------------------------------------------------------------------------

    public function test_can_list_products_paginated(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->createProduct(['sku' => "LIST-{$i}", 'name' => "Product {$i}"]);
        }

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
    }

    public function test_list_products_returns_only_own_organization(): void
    {
        $this->createProduct(['name' => 'Our Product']);

        $otherOrg = Organization::factory()->create();
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
        Product::create([
            'organization_id' => $otherOrg->id,
            'sku' => 'OTHER-001',
            'name' => 'Other Org Product',
            'type' => Product::TYPE_GOODS,
            'category_id' => $otherCategory->id,
            'unit_id' => $otherUnit->id,
            'purchase_price' => 10,
            'selling_price' => 20,
            'is_active' => true,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        foreach ($data as $product) {
            $this->assertEquals($this->organization->id, $product['organization_id']);
        }
    }

    public function test_list_products_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1' . $this->baseUrl, [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // CREATE (POST /products)
    // -------------------------------------------------------------------------

    public function test_can_create_product(): void
    {
        $response = $this->apiPost($this->baseUrl, $this->validProductPayload());

        $this->assertCreatedResponse($response);
        $response->assertJsonFragment(['sku' => 'PROD-001', 'name' => 'Test Product']);
        $this->assertDatabaseHas('products', [
            'organization_id' => $this->organization->id,
            'sku' => 'PROD-001',
        ]);
    }

    public function test_can_create_service_type_product(): void
    {
        $response = $this->apiPost($this->baseUrl, $this->validProductPayload([
            'sku' => 'SRV-001',
            'name' => 'Consulting Service',
            'type' => Product::TYPE_SERVICE,
        ]));

        $this->assertCreatedResponse($response);
        $response->assertJsonFragment(['type' => 'service']);
    }

    public function test_create_product_requires_mandatory_fields(): void
    {
        $response = $this->apiPost($this->baseUrl, []);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_product_validates_sku_required(): void
    {
        $payload = $this->validProductPayload();
        unset($payload['sku']);

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_product_validates_name_required(): void
    {
        $payload = $this->validProductPayload();
        unset($payload['name']);

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_product_validates_type(): void
    {
        $response = $this->apiPost($this->baseUrl, $this->validProductPayload([
            'type' => 'invalid_type',
        ]));

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_product_validates_prices_are_numeric(): void
    {
        $response = $this->apiPost($this->baseUrl, $this->validProductPayload([
            'purchase_price' => 'not_a_number',
            'selling_price' => 'also_bad',
        ]));

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_product_unauthenticated_returns_401(): void
    {
        $response = $this->postJson('/api/v1' . $this->baseUrl, $this->validProductPayload(), [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_create_product_without_permission_returns_403(): void
    {
        $this->setUpAuthenticatedUser([]);

        $response = $this->apiPost($this->baseUrl, $this->validProductPayload());

        $this->assertForbidden($response);
    }

    // -------------------------------------------------------------------------
    // SKU UNIQUENESS PER ORGANIZATION
    // -------------------------------------------------------------------------

    public function test_sku_must_be_unique_per_organization(): void
    {
        $this->createProduct(['sku' => 'UNIQUE-001']);

        $response = $this->apiPost($this->baseUrl, $this->validProductPayload([
            'sku' => 'UNIQUE-001',
        ]));

        $this->assertErrorResponse($response, 422);
    }

    public function test_same_sku_allowed_across_different_organizations(): void
    {
        $this->createProduct(['sku' => 'SHARED-SKU']);

        $otherOrg = Organization::factory()->create();
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

        // Directly create product in another org - should succeed
        $product = Product::create([
            'organization_id' => $otherOrg->id,
            'sku' => 'SHARED-SKU',
            'name' => 'Other Product',
            'type' => Product::TYPE_GOODS,
            'category_id' => $otherCategory->id,
            'unit_id' => $otherUnit->id,
            'purchase_price' => 10,
            'selling_price' => 20,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('products', [
            'organization_id' => $otherOrg->id,
            'sku' => 'SHARED-SKU',
        ]);
    }

    // -------------------------------------------------------------------------
    // SHOW (GET /products/{id})
    // -------------------------------------------------------------------------

    public function test_can_show_product(): void
    {
        $product = $this->createProduct(['name' => 'Show Me']);

        $response = $this->apiGet("{$this->baseUrl}/{$product->id}");

        $this->assertSuccessResponse($response);
        $response->assertJsonFragment(['name' => 'Show Me']);
    }

    public function test_show_product_from_another_organization_returns_404(): void
    {
        $otherOrg = Organization::factory()->create();
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
        $product = Product::create([
            'organization_id' => $otherOrg->id,
            'sku' => 'OTHER-001',
            'name' => 'Other Product',
            'type' => Product::TYPE_GOODS,
            'category_id' => $otherCategory->id,
            'unit_id' => $otherUnit->id,
            'purchase_price' => 10,
            'selling_price' => 20,
            'is_active' => true,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$product->id}");

        $this->assertErrorResponse($response, 404);
    }

    public function test_show_nonexistent_product_returns_404(): void
    {
        $response = $this->apiGet("{$this->baseUrl}/99999");

        $this->assertErrorResponse($response, 404);
    }

    // -------------------------------------------------------------------------
    // UPDATE (PUT /products/{id})
    // -------------------------------------------------------------------------

    public function test_can_update_product(): void
    {
        $product = $this->createProduct();

        $response = $this->apiPut("{$this->baseUrl}/{$product->id}", [
            'name' => 'Updated Product',
            'selling_price' => 150.00,
        ]);

        $this->assertSuccessResponse($response);
        $response->assertJsonFragment(['name' => 'Updated Product']);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product',
        ]);
    }

    public function test_cannot_update_product_from_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
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
        $product = Product::create([
            'organization_id' => $otherOrg->id,
            'sku' => 'O-001',
            'name' => 'Other',
            'type' => Product::TYPE_GOODS,
            'category_id' => $otherCategory->id,
            'unit_id' => $otherUnit->id,
            'purchase_price' => 10,
            'selling_price' => 20,
            'is_active' => true,
        ]);

        $response = $this->apiPut("{$this->baseUrl}/{$product->id}", [
            'name' => 'Hacked',
        ]);

        $this->assertErrorResponse($response, 404);
    }

    public function test_update_product_unauthenticated_returns_401(): void
    {
        $product = $this->createProduct();

        $response = $this->putJson("/api/v1{$this->baseUrl}/{$product->id}", [
            'name' => 'Updated',
        ], ['Accept' => 'application/json']);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // DELETE (DELETE /products/{id})
    // -------------------------------------------------------------------------

    public function test_can_delete_product(): void
    {
        $product = $this->createProduct();

        $response = $this->apiDelete("{$this->baseUrl}/{$product->id}");

        $this->assertSuccessResponse($response);
    }

    public function test_cannot_delete_product_from_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
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
        $product = Product::create([
            'organization_id' => $otherOrg->id,
            'sku' => 'DEL-001',
            'name' => 'Other',
            'type' => Product::TYPE_GOODS,
            'category_id' => $otherCategory->id,
            'unit_id' => $otherUnit->id,
            'purchase_price' => 10,
            'selling_price' => 20,
            'is_active' => true,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$product->id}");

        $this->assertErrorResponse($response, 404);
    }

    // -------------------------------------------------------------------------
    // STOCK LEVELS (GET /products/{id}/stock)
    // -------------------------------------------------------------------------

    public function test_can_get_product_stock_levels(): void
    {
        $product = $this->createProduct();
        $warehouse = Warehouse::create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        StockLevel::create([
            'organization_id' => $this->organization->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 10,
            'average_cost' => 50.00,
            'total_value' => 5000.00,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$product->id}/stock");

        $this->assertSuccessResponse($response);
    }

    // -------------------------------------------------------------------------
    // CLONE (POST /products/{id}/clone)
    // -------------------------------------------------------------------------

    public function test_can_clone_product(): void
    {
        $product = $this->createProduct([
            'sku' => 'ORIGINAL-001',
            'name' => 'Original Product',
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$product->id}/clone", [
            'sku' => 'CLONE-001',
            'name' => 'Cloned Product',
        ]);

        $this->assertCreatedResponse($response);
        $response->assertJsonFragment(['sku' => 'CLONE-001']);
    }

    public function test_clone_requires_new_sku(): void
    {
        $product = $this->createProduct(['sku' => 'ORIG-001']);

        $response = $this->apiPost("{$this->baseUrl}/{$product->id}/clone", [
            'sku' => 'ORIG-001', // Same SKU should fail
        ]);

        $this->assertErrorResponse($response, 422);
    }

    // -------------------------------------------------------------------------
    // REORDER LIST (GET /products/reorder-list)
    // -------------------------------------------------------------------------

    public function test_can_get_reorder_list(): void
    {
        $warehouse = Warehouse::create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'name' => 'Warehouse',
            'code' => 'WH-01',
            'is_default' => true,
            'is_active' => true,
        ]);

        $lowStockProduct = $this->createProduct([
            'sku' => 'LOW-001',
            'name' => 'Low Stock Item',
            'reorder_level' => 50,
            'reorder_quantity' => 100,
        ]);

        StockLevel::create([
            'organization_id' => $this->organization->id,
            'product_id' => $lowStockProduct->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
            'average_cost' => 50.00,
            'total_value' => 500.00,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/reorder-list");

        $this->assertSuccessResponse($response);
    }

    // -------------------------------------------------------------------------
    // BULK PRICE UPDATE (POST /products/bulk-update-prices)
    // -------------------------------------------------------------------------

    public function test_can_bulk_update_prices(): void
    {
        $product1 = $this->createProduct(['sku' => 'BULK-001']);
        $product2 = $this->createProduct(['sku' => 'BULK-002']);

        $response = $this->apiPost("{$this->baseUrl}/bulk-update-prices", [
            'updates' => [
                ['product_id' => $product1->id, 'selling_price' => 120.00],
                ['product_id' => $product2->id, 'selling_price' => 250.00],
            ],
        ]);

        $this->assertSuccessResponse($response);
    }

    public function test_bulk_update_prices_validates_payload(): void
    {
        $response = $this->apiPost("{$this->baseUrl}/bulk-update-prices", []);

        $this->assertErrorResponse($response, 422);
    }

    // -------------------------------------------------------------------------
    // SEARCH / FILTER
    // -------------------------------------------------------------------------

    public function test_can_search_products_by_name(): void
    {
        $this->createProduct(['sku' => 'S-001', 'name' => 'Wireless Mouse']);
        $this->createProduct(['sku' => 'S-002', 'name' => 'Wired Keyboard']);
        $this->createProduct(['sku' => 'S-003', 'name' => 'USB Cable']);

        $response = $this->apiGet("{$this->baseUrl}?search=Wireless");

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $product) {
            $this->assertStringContainsStringIgnoringCase('wireless', $product['name']);
        }
    }

    public function test_can_search_products_by_sku(): void
    {
        $this->createProduct(['sku' => 'FIND-ME-001', 'name' => 'Findable']);
        $this->createProduct(['sku' => 'IGNORE-002', 'name' => 'Ignorable']);

        $response = $this->apiGet("{$this->baseUrl}?search=FIND-ME");

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_can_filter_products_by_category(): void
    {
        $catA = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Category A',
            'is_active' => true,
        ]);
        $catB = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Category B',
            'is_active' => true,
        ]);

        $this->createProduct(['sku' => 'CAT-A-001', 'category_id' => $catA->id]);
        $this->createProduct(['sku' => 'CAT-B-001', 'category_id' => $catB->id]);

        $response = $this->apiGet("{$this->baseUrl}?category_id={$catA->id}");

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        foreach ($data as $product) {
            $this->assertEquals($catA->id, $product['category_id']);
        }
    }

    public function test_can_filter_products_by_type(): void
    {
        $this->createProduct(['sku' => 'GOODS-001', 'type' => Product::TYPE_GOODS]);
        $this->createProduct(['sku' => 'SVC-001', 'type' => Product::TYPE_SERVICE]);

        $response = $this->apiGet("{$this->baseUrl}?type=goods");

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        foreach ($data as $product) {
            $this->assertEquals('goods', $product['type']);
        }
    }

    public function test_can_filter_products_by_active_status(): void
    {
        $this->createProduct(['sku' => 'ACT-001', 'is_active' => true]);
        $this->createProduct(['sku' => 'INACT-001', 'is_active' => false]);

        $response = $this->apiGet("{$this->baseUrl}?is_active=1");

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        foreach ($data as $product) {
            $this->assertTrue($product['is_active']);
        }
    }
}
