<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\XbrlFiling;
use App\Models\Accounting\XbrlTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class XbrlTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.xbrl.view',
            'accounting.xbrl.manage',
            'accounting.xbrl.create',
            'accounting.xbrl.edit',
            'accounting.xbrl.submit',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTaxonomy(array $overrides = []): XbrlTaxonomy
    {
        return XbrlTaxonomy::create(array_merge([
            'organization_id' => $this->organization->id,
            'name'            => 'IFRS Taxonomy ' . fake()->unique()->numerify('###'),
            'version'         => '2023',
            'namespace'       => 'https://xbrl.ifrs.org/taxonomy/' . fake()->unique()->numerify('###'),
            'is_active'       => true,
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    private function makeFiscalYear(): FiscalYear
    {
        return FiscalYear::create([
            'organization_id' => $this->organization->id,
            'name'            => 'FY2025',
            'start_date'      => '2025-01-01',
            'end_date'        => '2025-12-31',
            'is_closed'       => false,
        ]);
    }

    private function makeFiling(XbrlTaxonomy $taxonomy, FiscalYear $fiscalYear, array $overrides = []): XbrlFiling
    {
        return XbrlFiling::create(array_merge([
            'organization_id' => $this->organization->id,
            'fiscal_year_id'  => $fiscalYear->id,
            'taxonomy_id'     => $taxonomy->id,
            'period_start'    => '2025-01-01',
            'period_end'      => '2025-12-31',
            'status'          => XbrlFiling::STATUS_DRAFT,
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Taxonomies Index
    // -------------------------------------------------------------------------

    public function test_taxonomies_index_returns_list(): void
    {
        $this->makeTaxonomy();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/xbrl/taxonomies');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Taxonomies Store
    // -------------------------------------------------------------------------

    public function test_taxonomies_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/xbrl/taxonomies', []);

        $response->assertStatus(422);
    }

    public function test_taxonomies_store_creates_taxonomy(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/xbrl/taxonomies', [
                'name'      => 'IFRS 2023',
                'version'   => '2023',
                'namespace' => 'https://xbrl.ifrs.org/taxonomy/2023',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Taxonomies Show/Update
    // -------------------------------------------------------------------------

    public function test_taxonomies_show_returns_details(): void
    {
        $taxonomy = $this->makeTaxonomy();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/xbrl/taxonomies/' . $taxonomy->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_taxonomies_update_modifies_taxonomy(): void
    {
        $taxonomy = $this->makeTaxonomy(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/xbrl/taxonomies/' . $taxonomy->uuid, [
                'name' => 'Updated Taxonomy',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Taxonomy', $taxonomy->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Filings Index
    // -------------------------------------------------------------------------

    public function test_filings_index_returns_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/xbrl/filings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Filings Store
    // -------------------------------------------------------------------------

    public function test_filings_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/xbrl/filings', []);

        $response->assertStatus(422);
    }

    public function test_filings_store_creates_filing(): void
    {
        $taxonomy   = $this->makeTaxonomy();
        $fiscalYear = $this->makeFiscalYear();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/xbrl/filings', [
                'fiscal_year_id' => $fiscalYear->id,
                'taxonomy_id'    => $taxonomy->id,
                'report_type'    => 'annual',
                'period_start'   => '2025-01-01',
                'period_end'     => '2025-12-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Filings Show
    // -------------------------------------------------------------------------

    public function test_filings_show_returns_details(): void
    {
        $taxonomy   = $this->makeTaxonomy();
        $fiscalYear = $this->makeFiscalYear();
        $filing     = $this->makeFiling($taxonomy, $fiscalYear);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/xbrl/filings/' . $filing->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_filings_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/xbrl/filings/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Validate
    // -------------------------------------------------------------------------

    public function test_validate_filing_returns_result(): void
    {
        $taxonomy   = $this->makeTaxonomy();
        $fiscalYear = $this->makeFiscalYear();
        $filing     = $this->makeFiling($taxonomy, $fiscalYear);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/xbrl/filings/' . $filing->uuid . '/validate');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/xbrl/taxonomies')->assertStatus(401);
    }
}
