<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\QInfoRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class QInfoRecordTest extends TestCase
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

    public function test_index_returns_records(): void
    {
        QInfoRecord::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/q-info-records', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_record(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/q-info-records',
            [
                'product_id'      => $this->product->id,
                'inspection_type' => 'goods_receipt',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('q_info_records', [
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
            'inspection_type' => 'goods_receipt',
        ]);
    }

    public function test_store_requires_product_id(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/q-info-records',
            ['inspection_type' => 'goods_receipt'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_valid_inspection_type(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/q-info-records',
            ['product_id' => $this->product->id, 'inspection_type' => 'invalid'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_record(): void
    {
        $record = QInfoRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/q-info-records/{$record->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_changes_notes(): void
    {
        $record = QInfoRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->putJson(
            "/api/v1/manufacturing/q-info-records/{$record->id}",
            ['notes' => 'Updated quality inspection notes'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('q_info_records', [
            'id'    => $record->id,
            'notes' => 'Updated quality inspection notes',
        ]);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_record(): void
    {
        $record = QInfoRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/q-info-records/{$record->id}",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted('q_info_records', ['id' => $record->id]);
    }

    // ─── dueForInspection ─────────────────────────────────────────────────────

    public function test_due_for_inspection_returns_data(): void
    {
        $response = $this->getJson(
            '/api/v1/manufacturing/q-info-records/due-for-inspection',
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/q-info-records')->assertUnauthorized();
    }
}
