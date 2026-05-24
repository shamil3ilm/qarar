<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class BomAlternativeTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_alternatives_for_product(): void
    {
        $response = $this->getJson(
            "/api/v1/manufacturing/bom-alternatives/{$this->product->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_alternative(): void
    {
        $response = $this->postJson(
            "/api/v1/manufacturing/bom-alternatives/{$this->product->id}",
            [
                'alternative_number' => 1,
                'valid_from'         => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('bom_alternatives', [
            'product_id'         => $this->product->id,
            'alternative_number' => 1,
        ]);
    }

    public function test_store_requires_valid_from(): void
    {
        $response = $this->postJson(
            "/api/v1/manufacturing/bom-alternatives/{$this->product->id}",
            ['alternative_number' => 2],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson("/api/v1/manufacturing/bom-alternatives/{$this->product->id}")->assertUnauthorized();
    }
}
