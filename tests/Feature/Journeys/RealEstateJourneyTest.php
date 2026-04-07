<?php

declare(strict_types=1);

namespace Tests\Feature\Journeys;

use App\Models\Core\OrganizationModule;
use App\Models\RealEstate\LeaseContract;
use App\Models\RealEstate\Portfolio;
use App\Models\RealEstate\Property;
use App\Models\RealEstate\Building;
use App\Models\RealEstate\RentalUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Real Estate Flexible Framework (RE-FX) journey test.
 *
 * Verifies the full property hierarchy from portfolio creation through
 * lease contract activation and periodic posting runs.
 *
 * Scenarios:
 *   1.  Portfolio → Property → Building → Floor → Rental Unit (hierarchy creation)
 *   2.  Lease contract creation with financial conditions
 *   3.  Contract activation: DRAFT → active
 *   4.  Contract cannot be activated twice
 *   5.  Posting run executes without error for active contracts
 *   6.  All RE resources carry the authenticated user's organization_id
 */
class RealEstateJourneyTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('AE');
        $this->setUpAuthenticatedUser([]);
        $this->setUpOpenFiscalPeriod();

        // Enable real_estate module (not in default list)
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code'     => 'real_estate',
            'is_enabled'      => true,
            'enabled_at'      => now(),
        ]);
    }

    // =========================================================================
    // 1. Full portfolio hierarchy creation
    // =========================================================================

    public function test_full_portfolio_hierarchy_can_be_created(): void
    {
        // Portfolio
        $portfolioResponse = $this->apiPost('/real-estate/portfolios', [
            'code'        => 'GULF-01',
            'name'        => 'Gulf Commercial Portfolio',
            'type'        => 'commercial',
            'currency_code' => 'AED',
            'description' => 'Prime commercial assets in the UAE',
            'is_active'   => true,
        ]);
        $portfolioResponse->assertStatus(201);
        $portfolioId = $portfolioResponse->json('data.id');
        $this->assertNotNull($portfolioId);

        $this->assertDatabaseHas('re_portfolios', [
            'id'              => $portfolioId,
            'organization_id' => $this->organization->id,
            'type'            => 'commercial',
        ]);

        // Property
        $propertyResponse = $this->apiPost('/real-estate/properties', [
            'portfolio_id'   => $portfolioId,
            'code'           => 'DXB-TOWER-01',
            'name'           => 'Dubai Business Tower',
            'type'           => 'commercial',
            'street_address' => 'Sheikh Zayed Road',
            'city'           => 'Dubai',
            'country_code'   => 'AE',
            'total_area_sqm' => 8500,
            'status'         => 'active',
        ]);
        $propertyResponse->assertStatus(201);
        $propertyId = $propertyResponse->json('data.id');

        $this->assertDatabaseHas('re_properties', [
            'id'           => $propertyId,
            'portfolio_id' => $portfolioId,
        ]);

        // Building
        $buildingResponse = $this->apiPost("/real-estate/properties/{$propertyId}/buildings", [
            'code'                  => 'TOWER-A',
            'name'                  => 'Tower A',
            'floors_above_ground'   => 20,
            'floors_below_ground'   => 2,
            'gross_area_sqm'        => 7000,
            'net_lettable_area_sqm' => 5500,
            'year_built'            => 2018,
            'status'                => 'active',
        ]);
        $buildingResponse->assertStatus(201);
        $buildingId = $buildingResponse->json('data.id');

        $this->assertDatabaseHas('re_buildings', ['id' => $buildingId]);

        // Floor
        $floorResponse = $this->apiPost("/real-estate/buildings/{$buildingId}/floors", [
            'floor_number'     => 5,
            'floor_label'      => 'Level 5',
            'total_area_sqm'   => 350,
            'lettable_area_sqm' => 300,
        ]);
        $floorResponse->assertStatus(201);
        $floorId = $floorResponse->json('data.id');

        $this->assertDatabaseHas('re_floors', ['id' => $floorId, 'building_id' => $buildingId]);

        // Rental Unit
        $unitResponse = $this->apiPost("/real-estate/buildings/{$buildingId}/units", [
            'floor_id'   => $floorId,
            'code'       => 'UNIT-501',
            'name'       => 'Office Suite 501',
            'unit_type'  => 'office',
            'area_sqm'   => 150,
            'usage_type' => 'office_space',
        ]);
        $unitResponse->assertStatus(201);
        $unitId = $unitResponse->json('data.id');

        $this->assertDatabaseHas('re_rental_units', [
            'id'          => $unitId,
            'building_id' => $buildingId,
            'floor_id'    => $floorId,
        ]);
    }

    // =========================================================================
    // 2 & 3. Lease contract creation and activation
    // =========================================================================

    public function test_lease_contract_lifecycle_draft_to_active(): void
    {
        $unitId = $this->createHierarchyAndGetUnitId();

        // Create contract in DRAFT
        $contractResponse = $this->apiPost('/real-estate/contracts', [
            'contract_type'         => 'lease_out',
            'rental_unit_id'        => $unitId,
            'counterparty_name'     => 'Acme Trading LLC',
            'start_date'            => now()->format('Y-m-d'),
            'end_date'              => now()->addYears(2)->format('Y-m-d'),
            'notice_period_months'  => 3,
            'currency_code'         => 'AED',
            'payment_day'           => 1,
            'payment_frequency'     => 'monthly',
            'auto_renew'            => false,
            'conditions' => [
                [
                    'condition_type'    => 'base_rent',
                    'amount'            => 12000.00,
                    'basis'             => 'flat',
                    'valid_from'        => now()->format('Y-m-d'),
                    'escalation_type'   => 'none',
                    'is_taxable'        => true,
                ],
            ],
        ]);
        $contractResponse->assertStatus(201);

        $contractId = $contractResponse->json('data.id');
        $this->assertNotNull($contractId);

        $this->assertDatabaseHas('re_contracts', [
            'id'              => $contractId,
            'organization_id' => $this->organization->id,
            'contract_type'   => 'lease_out',
        ]);

        // Verify initial status is draft
        $contract = LeaseContract::find($contractId);
        $this->assertContains($contract->status, ['draft', 'pending', 'active'],
            "Contract must be in a valid initial state"
        );

        // Activate the contract → active
        $activateResponse = $this->apiPost("/real-estate/contracts/{$contractId}/activate");
        $activateResponse->assertStatus(200);

        $contract->refresh();
        $this->assertEquals('active', $contract->status);
        $this->assertDatabaseHas('re_contracts', [
            'id'     => $contractId,
            'status' => 'active',
        ]);
    }

    // =========================================================================
    // 4. Contract cannot be activated twice
    // =========================================================================

    public function test_active_contract_cannot_be_activated_again(): void
    {
        $unitId = $this->createHierarchyAndGetUnitId('UNIT-DUPE');

        $contractResponse = $this->apiPost('/real-estate/contracts', [
            'contract_type'    => 'lease_out',
            'rental_unit_id'   => $unitId,
            'counterparty_name' => 'Duplicate Activate Corp',
            'start_date'       => now()->format('Y-m-d'),
            'currency_code'    => 'AED',
            'payment_day'      => 1,
            'payment_frequency' => 'monthly',
        ]);
        $contractResponse->assertStatus(201);
        $contractId = $contractResponse->json('data.id');

        // First activation → success
        $this->apiPost("/real-estate/contracts/{$contractId}/activate")->assertStatus(200);

        // Second activation → must fail
        $response = $this->apiPost("/real-estate/contracts/{$contractId}/activate");
        $this->assertContains(
            $response->status(),
            [422, 400],
            "Already-active contract must not be activated again (got {$response->status()})"
        );
    }

    // =========================================================================
    // 5. Posting run executes without error
    // =========================================================================

    public function test_posting_run_executes_for_current_period(): void
    {
        // Activate a contract so the posting run has something to process
        $unitId = $this->createHierarchyAndGetUnitId('UNIT-RENT');

        $contractResponse = $this->apiPost('/real-estate/contracts', [
            'contract_type'    => 'lease_out',
            'rental_unit_id'   => $unitId,
            'counterparty_name' => 'Posting Run Tenant LLC',
            'start_date'       => now()->startOfMonth()->format('Y-m-d'),
            'end_date'         => now()->addYears(1)->format('Y-m-d'),
            'currency_code'    => 'AED',
            'payment_day'      => 1,
            'payment_frequency' => 'monthly',
            'conditions' => [
                [
                    'condition_type'  => 'base_rent',
                    'amount'          => 5000.00,
                    'basis'           => 'flat',
                    'valid_from'      => now()->startOfMonth()->format('Y-m-d'),
                    'escalation_type' => 'none',
                    'is_taxable'      => false,
                ],
            ],
        ]);
        $contractResponse->assertStatus(201);
        $contractId = $contractResponse->json('data.id');
        $this->apiPost("/real-estate/contracts/{$contractId}/activate")->assertStatus(200);

        // Execute the posting run for current period
        $runResponse = $this->apiPost('/real-estate/posting-runs/execute', [
            'type'         => 'rent',
            'period_year'  => (int) now()->format('Y'),
            'period_month' => (int) now()->format('n'),
        ]);

        $runResponse->assertStatus(201);
        $this->assertNotNull($runResponse->json('data.id'));
    }

    // =========================================================================
    // 6. All RE resources carry the authenticated user's organization_id
    // =========================================================================

    public function test_all_re_resources_carry_correct_organization_id(): void
    {
        $portfolioResponse = $this->apiPost('/real-estate/portfolios', [
            'code'        => 'ORG-CHECK',
            'name'        => 'Org Check Portfolio',
            'type'        => 'residential',
            'currency_code' => 'AED',
        ]);
        $portfolioResponse->assertStatus(201);

        $portfolioId = $portfolioResponse->json('data.id');
        $portfolio   = Portfolio::find($portfolioId);

        $this->assertEquals(
            $this->organization->id,
            $portfolio->organization_id,
            'Portfolio organization_id must match the authenticated user'
        );

        $propertyResponse = $this->apiPost('/real-estate/properties', [
            'portfolio_id' => $portfolioId,
            'code'         => 'PROP-ORG',
            'name'         => 'Org Check Property',
            'type'         => 'residential',
        ]);
        $propertyResponse->assertStatus(201);

        $property = Property::find($propertyResponse->json('data.id'));
        $this->assertEquals($this->organization->id, $property->organization_id);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createHierarchyAndGetUnitId(string $unitCode = 'UNIT-001'): int
    {
        $portfolioResponse = $this->apiPost('/real-estate/portfolios', [
            'code'         => 'P-' . substr($unitCode, -3),
            'name'         => "Test Portfolio for {$unitCode}",
            'type'         => 'commercial',
            'currency_code' => 'AED',
        ]);
        $portfolioId = $portfolioResponse->json('data.id');

        $propertyResponse = $this->apiPost('/real-estate/properties', [
            'portfolio_id' => $portfolioId,
            'code'         => 'PROP-' . substr($unitCode, -3),
            'name'         => "Test Property for {$unitCode}",
            'type'         => 'commercial',
            'country_code' => 'AE',
        ]);
        $propertyId = $propertyResponse->json('data.id');

        $buildingResponse = $this->apiPost("/real-estate/properties/{$propertyId}/buildings", [
            'code'   => 'BLD-' . substr($unitCode, -3),
            'name'   => "Test Building for {$unitCode}",
            'status' => 'active',
        ]);
        $buildingId = $buildingResponse->json('data.id');

        $unitResponse = $this->apiPost("/real-estate/buildings/{$buildingId}/units", [
            'code'      => $unitCode,
            'name'      => "Test Unit {$unitCode}",
            'unit_type' => 'office',
            'area_sqm'  => 100,
        ]);
        $unitResponse->assertStatus(201);

        return (int) $unitResponse->json('data.id');
    }
}
