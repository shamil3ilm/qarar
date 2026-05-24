<?php

declare(strict_types=1);

namespace Tests\Feature\Tax;

use App\Models\Tax\Gstr9Return;
use App\Services\Tax\Gstr9ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class Gstr9ReturnTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Gstr9ReturnService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('IN');
        $this->setUpAuthenticatedUser();
        $this->service = app(Gstr9ReturnService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // createManual()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_create_manual_persists_to_database(): void
    {
        $return = $this->service->createManual([
            'organization_id'      => $this->organization->id,
            'gstin'                => '27AAPFU0939F1ZV',
            'financial_year_start' => 2024,
            't4a_taxable_supplies' => 5_000_000.0,
            't9_cgst_payable'      => 450_000.0,
            't9_sgst_payable'      => 450_000.0,
            't6a_itc_inputs'       => 800_000.0,
        ], $this->user->id);

        $this->assertDatabaseHas('gstr9_returns', [
            'organization_id'      => $this->organization->id,
            'gstin'                => '27AAPFU0939F1ZV',
            'financial_year_start' => 2024,
            'status'               => 'draft',
        ]);
    }

    public function test_create_manual_computes_t6_total_itc(): void
    {
        $return = $this->service->createManual([
            'organization_id'        => $this->organization->id,
            'gstin'                  => '27AAPFU0939F1ZV',
            'financial_year_start'   => 2024,
            't6a_itc_inputs'         => 300_000.0,
            't6b_itc_input_services' => 100_000.0,
            't6c_itc_capital_goods'  => 50_000.0,
        ], $this->user->id);

        $this->assertEquals(450_000.0, $return->t6_total_itc);
    }

    public function test_create_manual_computes_net_itc_after_reversal(): void
    {
        // ITC = 500k, reversed = 50k → net = 450k
        $return = $this->service->createManual([
            'organization_id'      => $this->organization->id,
            'gstin'                => '27AAPFU0939F1ZV',
            'financial_year_start' => 2024,
            't6a_itc_inputs'       => 500_000.0,
            't7_itc_reversed'      => 50_000.0,
        ], $this->user->id);

        $this->assertEquals(450_000.0, $return->net_itc);
    }

    public function test_create_manual_due_date_is_dec_31_of_following_year(): void
    {
        $return = $this->service->createManual([
            'organization_id'      => $this->organization->id,
            'gstin'                => '27AAPFU0939F1ZV',
            'financial_year_start' => 2024,
        ], $this->user->id);

        $this->assertEquals('2025-12-31', $return->due_date->format('Y-m-d'));
    }

    public function test_create_manual_throws_for_invalid_gstin(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('GSTIN must be 15 characters');

        $this->service->createManual([
            'organization_id'      => $this->organization->id,
            'gstin'                => 'INVALID',
            'financial_year_start' => 2024,
        ], $this->user->id);
    }

    public function test_create_manual_updates_existing_draft(): void
    {
        $first = $this->service->createManual([
            'organization_id'      => $this->organization->id,
            'gstin'                => '27AAPFU0939F1ZV',
            'financial_year_start' => 2024,
            't4a_taxable_supplies' => 1_000_000.0,
        ], $this->user->id);

        $second = $this->service->createManual([
            'organization_id'      => $this->organization->id,
            'gstin'                => '27AAPFU0939F1ZV',
            'financial_year_start' => 2024,
            't4a_taxable_supplies' => 2_000_000.0,
        ], $this->user->id);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(2_000_000.0, $second->t4a_taxable_supplies);
    }

    public function test_create_manual_throws_if_already_filed(): void
    {
        Gstr9Return::factory()->filed()->create([
            'organization_id'      => $this->organization->id,
            'gstin'                => '27AAPFU0939F1ZV',
            'financial_year_start' => 2024,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("status 'filed'");

        $this->service->createManual([
            'organization_id'      => $this->organization->id,
            'gstin'                => '27AAPFU0939F1ZV',
            'financial_year_start' => 2024,
        ], $this->user->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // fileReturn()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_file_return_transitions_to_filed(): void
    {
        $return = Gstr9Return::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => Gstr9Return::STATUS_DRAFT,
        ]);

        $filed = $this->service->fileReturn($return, 'AA2724250012345');

        $this->assertEquals(Gstr9Return::STATUS_FILED, $filed->status);
        $this->assertEquals('AA2724250012345', $filed->gstn_arn);
        $this->assertNotNull($filed->filed_date);
    }

    public function test_file_return_throws_if_not_draft(): void
    {
        $return = Gstr9Return::factory()->filed()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->fileReturn($return);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Model helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function test_financial_year_label(): void
    {
        $return = Gstr9Return::factory()->make([
            'organization_id'      => $this->organization->id,
            'financial_year_start' => 2024,
        ]);

        $this->assertEquals('FY 2024-25', $return->financialYearLabel());
    }

    public function test_total_tax_payable_sums_all_components(): void
    {
        $return = Gstr9Return::factory()->make([
            'organization_id'  => $this->organization->id,
            't9_igst_payable'  => 100_000.0,
            't9_cgst_payable'  => 200_000.0,
            't9_sgst_payable'  => 200_000.0,
            't9_cess_payable'  => 10_000.0,
        ]);

        $this->assertEquals(510_000.0, $return->totalTaxPayable());
    }

    public function test_net_tax_liability_after_itc_setoff(): void
    {
        $return = Gstr9Return::factory()->make([
            'organization_id' => $this->organization->id,
            't9_cgst_payable' => 100_000.0,
            't9_sgst_payable' => 100_000.0,
            't9_igst_payable' => 0.0,
            't9_cess_payable' => 0.0,
            'net_itc'         => 150_000.0,
        ]);

        // Total payable 200k - ITC 150k = net liability 50k
        $this->assertEquals(50_000.0, $return->netTaxLiability());
    }

    public function test_net_tax_liability_cannot_be_negative(): void
    {
        $return = Gstr9Return::factory()->make([
            'organization_id' => $this->organization->id,
            't9_cgst_payable' => 50_000.0,
            't9_sgst_payable' => 50_000.0,
            't9_igst_payable' => 0.0,
            't9_cess_payable' => 0.0,
            'net_itc'         => 500_000.0,  // ITC > tax — refund scenario
        ]);

        $this->assertEquals(0.0, $return->netTaxLiability());
    }
}
