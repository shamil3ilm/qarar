<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CoProductTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private BomTemplate $bom;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();

        $this->bom = BomTemplate::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    // ─── bom index ────────────────────────────────────────────────────────────

    public function test_index_for_bom_returns_list(): void
    {
        $response = $this->getJson(
            "/api/v1/manufacturing/co-products/bom/{$this->bom->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── bom add ──────────────────────────────────────────────────────────────

    public function test_add_to_bom_creates_co_product(): void
    {
        $response = $this->postJson(
            "/api/v1/manufacturing/co-products/bom/{$this->bom->id}",
            [
                'product_id'         => $this->product->id,
                'quantity_per_base'  => 0.5,
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('bom_co_products', [
            'bom_template_id' => $this->bom->id,
            'product_id'      => $this->product->id,
        ]);
    }

    public function test_add_to_bom_requires_product_id_and_quantity(): void
    {
        $response = $this->postJson(
            "/api/v1/manufacturing/co-products/bom/{$this->bom->id}",
            [],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── work order index ─────────────────────────────────────────────────────

    public function test_index_for_work_order_returns_list(): void
    {
        $response = $this->getJson(
            '/api/v1/manufacturing/co-products/work-order/9999',
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson("/api/v1/manufacturing/co-products/bom/{$this->bom->id}")->assertUnauthorized();
    }
}
