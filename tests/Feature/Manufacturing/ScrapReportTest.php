<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\ScrapReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ScrapReportTest extends TestCase
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

    public function test_index_returns_scrap_reports(): void
    {
        ScrapReport::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/scrap-reports', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_scrap_report(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/scrap-reports',
            [
                'product_id'     => $this->product->id,
                'scrap_date'     => now()->format('Y-m-d'),
                'scrap_quantity' => 5,
                'scrap_cause'    => 'defect',
                'description'    => 'Defective units from line 3',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('scrap_reports', [
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);
    }

    public function test_store_requires_product_id(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/scrap-reports',
            [
                'scrap_date'     => now()->format('Y-m-d'),
                'scrap_quantity' => 5,
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_scrap_date(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/scrap-reports',
            ['product_id' => $this->product->id, 'scrap_quantity' => 5],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_scrap_quantity(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/scrap-reports',
            ['product_id' => $this->product->id, 'scrap_date' => now()->format('Y-m-d')],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_rejects_product_from_other_org(): void
    {
        $otherProduct = Product::factory()->create(); // different org

        $response = $this->postJson(
            '/api/v1/manufacturing/scrap-reports',
            [
                'product_id'     => $otherProduct->id,
                'scrap_date'     => now()->format('Y-m-d'),
                'scrap_quantity' => 5,
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_scrap_report(): void
    {
        $report = ScrapReport::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/scrap-reports/{$report->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_changes_description(): void
    {
        $report = ScrapReport::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->putJson(
            "/api/v1/manufacturing/scrap-reports/{$report->id}",
            ['description' => 'Updated scrap description'],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('scrap_reports', [
            'id'          => $report->id,
            'description' => 'Updated scrap description',
        ]);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_scrap_report(): void
    {
        $report = ScrapReport::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->product->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/scrap-reports/{$report->id}",
            [],
            $this->authHeaders()
        );

        $response->assertNoContent();
        $this->assertSoftDeleted('scrap_reports', ['id' => $report->id]);
    }

    // ─── summary ─────────────────────────────────────────────────────────────

    public function test_summary_returns_data(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/scrap-reports/summary', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/scrap-reports')->assertUnauthorized();
    }
}
