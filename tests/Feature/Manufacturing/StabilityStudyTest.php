<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\StabilityStudy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class StabilityStudyTest extends TestCase
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

    public function test_index_returns_studies(): void
    {
        StabilityStudy::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/stability-studies', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_study(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/stability-studies',
            [
                'study_number'    => 'SS-2026-001',
                'product_id'      => $this->product->id,
                'study_type'      => 'real_time',
                'start_date'      => now()->format('Y-m-d'),
                'planned_end_date' => now()->addYear()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('stability_studies', [
            'study_number'    => 'SS-2026-001',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_requires_study_number(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/stability-studies',
            [
                'product_id' => $this->product->id,
                'study_type' => 'real_time',
                'start_date' => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_product_id(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/stability-studies',
            [
                'study_number' => 'SS-2026-002',
                'study_type'   => 'real_time',
                'start_date'   => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_study(): void
    {
        $study = StabilityStudy::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/stability-studies/{$study->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_changes_notes(): void
    {
        $study = StabilityStudy::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->putJson(
            "/api/v1/manufacturing/stability-studies/{$study->id}",
            ['notes' => 'Updated study notes'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('stability_studies', [
            'id'    => $study->id,
            'notes' => 'Updated study notes',
        ]);
    }

    // ─── activate ─────────────────────────────────────────────────────────────

    public function test_activate_changes_status(): void
    {
        $study = StabilityStudy::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
            'status'          => StabilityStudy::STATUS_PLANNED,
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/stability-studies/{$study->id}/activate",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('stability_studies', [
            'id'     => $study->id,
            'status' => StabilityStudy::STATUS_ACTIVE,
        ]);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/stability-studies')->assertUnauthorized();
    }
}
