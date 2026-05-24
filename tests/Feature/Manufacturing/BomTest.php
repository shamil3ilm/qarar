<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class BomTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.bom.view',
            'manufacturing.bom.create',
            'manufacturing.bom.edit',
            'manufacturing.bom.delete',
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    private function makeBomPayload(array $overrides = []): array
    {
        return array_merge([
            'name'            => 'Test Assembly BOM',
            'product_id'      => $this->product->id,
            'output_quantity' => 100,
            'lines'           => [
                [
                    'product_id' => $this->product->id,
                    'quantity'   => 5,
                ],
            ],
        ], $overrides);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_boms(): void
    {
        BomTemplate::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/bom-templates', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(3, $response->json('data'));
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_draft_bom(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/bom-templates',
            $this->makeBomPayload(),
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('bom_templates', [
            'name'            => 'Test Assembly BOM',
            'organization_id' => $this->organization->id,
            'status'          => BomTemplate::STATUS_DRAFT,
        ]);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/bom-templates',
            $this->makeBomPayload(['name' => '']),
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_product_id(): void
    {
        $payload = $this->makeBomPayload();
        unset($payload['product_id']);

        $response = $this->postJson('/api/v1/manufacturing/bom-templates', $payload, $this->authHeaders());

        $response->assertUnprocessable();
    }

    public function test_store_requires_at_least_one_line(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/bom-templates',
            $this->makeBomPayload(['lines' => []]),
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_bom(): void
    {
        $bom = BomTemplate::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->getJson(
            "/api/v1/manufacturing/bom-templates/{$bom->uuid}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_other_org(): void
    {
        $bom = BomTemplate::factory()->create(); // different org

        $response = $this->getJson(
            "/api/v1/manufacturing/bom-templates/{$bom->uuid}",
            $this->authHeaders()
        );

        $response->assertNotFound();
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_changes_name(): void
    {
        $bom = BomTemplate::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->putJson(
            "/api/v1/manufacturing/bom-templates/{$bom->uuid}",
            ['name' => 'Updated BOM Name'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('bom_templates', [
            'id'   => $bom->id,
            'name' => 'Updated BOM Name',
        ]);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_draft_bom(): void
    {
        $bom = BomTemplate::factory()->draft()->create(['organization_id' => $this->organization->id]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/bom-templates/{$bom->uuid}",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted('bom_templates', ['id' => $bom->id]);
    }

    public function test_destroy_rejects_active_bom(): void
    {
        $bom = BomTemplate::factory()->active()->create(['organization_id' => $this->organization->id]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/bom-templates/{$bom->uuid}",
            [],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── setActive ────────────────────────────────────────────────────────────

    public function test_set_active_activates_draft_bom(): void
    {
        $bom = BomTemplate::factory()->draft()->create(['organization_id' => $this->organization->id]);

        $response = $this->patchJson(
            "/api/v1/manufacturing/bom-templates/{$bom->uuid}/active",
            ['active' => true],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('bom_templates', [
            'id'     => $bom->id,
            'status' => BomTemplate::STATUS_ACTIVE,
        ]);
    }

    // ─── duplicate ────────────────────────────────────────────────────────────

    public function test_duplicate_creates_copy(): void
    {
        $bom = BomTemplate::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->postJson(
            "/api/v1/manufacturing/bom-templates/{$bom->uuid}/duplicate",
            [],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertEquals(2, BomTemplate::where('organization_id', $this->organization->id)->count());
    }

    // ─── forProduct ───────────────────────────────────────────────────────────

    public function test_for_product_returns_boms_for_product(): void
    {
        BomTemplate::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/bom-templates/for-product?product_id={$this->product->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/bom-templates')->assertUnauthorized();
    }
}
