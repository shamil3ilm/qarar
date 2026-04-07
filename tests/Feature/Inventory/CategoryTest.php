<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Core\Organization;
use App\Models\Core\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CategoryTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private string $baseUrl = '/inventory/categories';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.categories.view',
            'inventory.categories.create',
            'inventory.categories.edit',
            'inventory.categories.delete',
        ]);
    }

    // -------------------------------------------------------------------------
    // LIST (GET /categories)
    // -------------------------------------------------------------------------

    public function test_can_list_categories(): void
    {
        Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Electronics',
            'description' => 'Electronic goods',
            'is_active' => true,
        ]);

        Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Clothing',
            'description' => 'Apparel items',
            'is_active' => true,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertSuccessResponse($response);
        $response->assertJsonCount(2, 'data');
    }

    public function test_list_categories_returns_only_own_organization(): void
    {
        Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Our Category',
            'is_active' => true,
        ]);

        $otherOrg = Organization::factory()->create();
        Category::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Org Category',
            'is_active' => true,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Our Category', $data[0]['name']);
    }

    public function test_list_categories_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1' . $this->baseUrl, [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // CREATE (POST /categories)
    // -------------------------------------------------------------------------

    public function test_can_create_category(): void
    {
        $payload = [
            'name' => 'Electronics',
            'description' => 'Electronic goods and appliances',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $response->assertJsonFragment(['name' => 'Electronics']);
        $this->assertDatabaseHas('categories', [
            'organization_id' => $this->organization->id,
            'name' => 'Electronics',
        ]);
    }

    public function test_can_create_child_category(): void
    {
        $parent = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Electronics',
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Mobile Phones',
            'parent_id' => $parent->id,
            'description' => 'Smartphones and basic phones',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $response->assertJsonFragment([
            'name' => 'Mobile Phones',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_create_category_requires_name(): void
    {
        $response = $this->apiPost($this->baseUrl, [
            'description' => 'No name provided',
        ]);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_category_unauthenticated_returns_401(): void
    {
        $response = $this->postJson('/api/v1' . $this->baseUrl, [
            'name' => 'Test',
        ], ['Accept' => 'application/json']);

        $this->assertUnauthorized($response);
    }

    public function test_create_category_without_permission_returns_403(): void
    {
        $this->setUpAuthenticatedUser([]);

        $response = $this->apiPost($this->baseUrl, [
            'name' => 'Unauthorized Category',
        ]);

        $this->assertForbidden($response);
    }

    // -------------------------------------------------------------------------
    // SHOW (GET /categories/{id})
    // -------------------------------------------------------------------------

    public function test_can_show_category(): void
    {
        $category = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Electronics',
            'description' => 'Electronic goods',
            'is_active' => true,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$category->id}");

        $this->assertSuccessResponse($response);
        $response->assertJsonFragment(['name' => 'Electronics']);
    }

    public function test_show_category_from_another_organization_returns_404(): void
    {
        $otherOrg = Organization::factory()->create();
        $category = Category::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Category',
            'is_active' => true,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$category->id}");

        $this->assertErrorResponse($response, 404);
    }

    public function test_show_nonexistent_category_returns_404(): void
    {
        $response = $this->apiGet("{$this->baseUrl}/99999");

        $this->assertErrorResponse($response, 404);
    }

    // -------------------------------------------------------------------------
    // UPDATE (PUT /categories/{id})
    // -------------------------------------------------------------------------

    public function test_can_update_category(): void
    {
        $category = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Old Name',
            'is_active' => true,
        ]);

        $response = $this->apiPut("{$this->baseUrl}/{$category->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $this->assertSuccessResponse($response);
        $response->assertJsonFragment(['name' => 'Updated Name']);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_cannot_update_category_from_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $category = Category::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Category',
            'is_active' => true,
        ]);

        $response = $this->apiPut("{$this->baseUrl}/{$category->id}", [
            'name' => 'Hacked Name',
        ]);

        $this->assertErrorResponse($response, 404);
    }

    public function test_update_category_unauthenticated_returns_401(): void
    {
        $category = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1{$this->baseUrl}/{$category->id}", [
            'name' => 'Updated',
        ], ['Accept' => 'application/json']);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // DELETE (DELETE /categories/{id})
    // -------------------------------------------------------------------------

    public function test_can_delete_category(): void
    {
        $category = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'To Delete',
            'is_active' => true,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$category->id}");

        $this->assertSuccessResponse($response);
    }

    public function test_cannot_delete_category_from_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $category = Category::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Category',
            'is_active' => true,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$category->id}");

        $this->assertErrorResponse($response, 404);
    }

    public function test_cannot_delete_category_with_products(): void
    {
        $category = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Has Products',
            'is_active' => true,
        ]);

        $unit = UnitOfMeasure::create([
            'organization_id' => $this->organization->id,
            'name' => 'Piece',
            'symbol' => 'pc',
            'conversion_factor' => 1,
            'is_active' => true,
        ]);

        Product::create([
            'organization_id' => $this->organization->id,
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'type' => Product::TYPE_GOODS,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 10.00,
            'selling_price' => 20.00,
            'is_active' => true,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$category->id}");

        $this->assertErrorResponse($response, 422);
    }

    public function test_delete_category_unauthenticated_returns_401(): void
    {
        $category = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1{$this->baseUrl}/{$category->id}", [], [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // MOVE (POST /categories/{id}/move)
    // -------------------------------------------------------------------------

    public function test_can_move_category_to_another_parent(): void
    {
        $parentA = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Parent A',
            'is_active' => true,
        ]);

        $parentB = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Parent B',
            'is_active' => true,
        ]);

        $child = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Child',
            'parent_id' => $parentA->id,
            'is_active' => true,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$child->id}/move", [
            'parent_id' => $parentB->id,
        ]);

        $this->assertSuccessResponse($response);
        $child->refresh();
        $this->assertEquals($parentB->id, $child->parent_id);
    }

    public function test_can_move_category_to_root(): void
    {
        $parent = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Parent',
            'is_active' => true,
        ]);

        $child = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Child',
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$child->id}/move", [
            'parent_id' => null,
        ]);

        $this->assertSuccessResponse($response);
        $child->refresh();
        $this->assertNull($child->parent_id);
    }

    // -------------------------------------------------------------------------
    // HIERARCHICAL STRUCTURE TESTS
    // -------------------------------------------------------------------------

    public function test_hierarchical_categories_structure(): void
    {
        $root = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Electronics',
            'is_active' => true,
        ]);

        $level2 = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Phones',
            'parent_id' => $root->id,
            'is_active' => true,
        ]);

        $level3 = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Smartphones',
            'parent_id' => $level2->id,
            'is_active' => true,
        ]);

        $this->assertEquals(1, $root->level);
        $this->assertEquals($root->id, $level2->parent_id);
        $this->assertEquals($level2->id, $level3->parent_id);
    }

    public function test_category_children_relationship(): void
    {
        $parent = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Parent',
            'is_active' => true,
        ]);

        Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Child A',
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Child B',
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $parent->refresh();
        $this->assertCount(2, $parent->children);
    }

    public function test_category_slug_is_auto_generated(): void
    {
        $response = $this->apiPost($this->baseUrl, [
            'name' => 'Electronics & Appliances',
        ]);

        $this->assertCreatedResponse($response);
        $this->assertDatabaseHas('categories', [
            'organization_id' => $this->organization->id,
            'slug' => 'electronics-appliances',
        ]);
    }
}
