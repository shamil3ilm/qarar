<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CopaPlanVersion;
use App\Models\Accounting\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CopaTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeFiscalYear(int $year = 2025): FiscalYear
    {
        return FiscalYear::factory()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'FY ' . $year,
            'start_date'      => "{$year}-01-01",
            'end_date'        => "{$year}-12-31",
            'is_closed'       => false,
            'is_current'      => true,
        ]);
    }

    private function makePlanVersion(FiscalYear $fy, array $overrides = []): CopaPlanVersion
    {
        return CopaPlanVersion::create(array_merge([
            'organization_id' => $this->organization->id,
            'fiscal_year_id'  => $fy->id,
            'version_name'    => 'Budget ' . $fy->name,
            'is_active'       => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Profitability
    // -------------------------------------------------------------------------

    public function test_profitability_returns_empty_result_for_new_org(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/copa/profitability');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_profitability_validates_period_range(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/copa/profitability?period=13'); // > 12

        $response->assertStatus(422);
    }

    public function test_profitability_accepts_date_filter(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/copa/profitability?from_date=2025-01-01&to_date=2025-12-31');

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Dimension breakdown
    // -------------------------------------------------------------------------

    public function test_dimension_breakdown_returns_result(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/copa/dimension/product_id');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Plan Versions — index
    // -------------------------------------------------------------------------

    public function test_plan_versions_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/copa/plan-versions');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_plan_versions_returns_versions(): void
    {
        $fy = $this->makeFiscalYear();
        $this->makePlanVersion($fy);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/copa/plan-versions');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Plan Versions — store
    // -------------------------------------------------------------------------

    public function test_store_plan_version_creates_version(): void
    {
        $fy = $this->makeFiscalYear();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/copa/plan-versions', [
                'version_name'   => 'Q1 Budget',
                'fiscal_year_id' => $fy->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.version_name', 'Q1 Budget');
    }

    public function test_store_plan_version_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/copa/plan-versions', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Plan Items — store
    // -------------------------------------------------------------------------

    public function test_store_plan_items_bulk_creates_lines(): void
    {
        $fy      = $this->makeFiscalYear();
        $version = $this->makePlanVersion($fy);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/copa/plan-versions/' . $version->id . '/items', [
                'lines' => [
                    [
                        'period'           => 1,
                        'planned_revenue'  => 500000,
                        'planned_cogs'     => 300000,
                    ],
                    [
                        'period'           => 2,
                        'planned_revenue'  => 600000,
                        'planned_cogs'     => 350000,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_store_plan_items_validates_lines_required(): void
    {
        $fy      = $this->makeFiscalYear();
        $version = $this->makePlanVersion($fy);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/copa/plan-versions/' . $version->id . '/items', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Variance
    // -------------------------------------------------------------------------

    public function test_variance_report_returns_result(): void
    {
        $fy      = $this->makeFiscalYear();
        $version = $this->makePlanVersion($fy);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/copa/variance?fiscal_year_id=' . $fy->id . '&plan_version_id=' . $version->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_variance_report_validates_required_ids(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/copa/variance');

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/copa/profitability')->assertStatus(401);
        $this->getJson('/api/v1/copa/plan-versions')->assertStatus(401);
    }
}
