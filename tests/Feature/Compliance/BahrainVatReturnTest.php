<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Models\Compliance\BahrainVatReturn;
use App\Services\Compliance\BahrainVatReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class BahrainVatReturnTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private BahrainVatReturnService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('BH'); // Bahrain org
        $this->setUpAuthenticatedUser();
        $this->service = app(BahrainVatReturnService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Constants
    // ─────────────────────────────────────────────────────────────────────────

    public function test_vat_rate_constant_is_10_percent(): void
    {
        $this->assertEquals(10.0, BahrainVatReturnService::VAT_RATE_PCT);
    }

    public function test_model_vat_rate_is_10_percent(): void
    {
        $this->assertEquals(10.0, BahrainVatReturn::VAT_RATE);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // calculate()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_calculate_output_vat_is_10_percent_of_supplies(): void
    {
        $result = $this->service->calculate(100_000.0);

        $this->assertEquals(10_000.0, $result['output_vat']);
    }

    public function test_calculate_net_vat_payable_is_output_minus_input(): void
    {
        // Supplies 100k → output VAT 10k; purchases 50k → input VAT 5k; net = 5k
        $result = $this->service->calculate(100_000.0, 0, 0, 50_000.0);

        $this->assertEquals(10_000.0, $result['output_vat']);
        $this->assertEquals(5_000.0, $result['total_input_vat']);
        $this->assertEquals(5_000.0, $result['net_vat_payable']);
    }

    public function test_calculate_capital_goods_input_tax_added_to_input(): void
    {
        // Purchases 0, capital goods 2,000 → input = 2,000; net = 10,000 - 2,000 = 8,000
        $result = $this->service->calculate(100_000.0, 0, 0, 0, 2_000.0);

        $this->assertEquals(2_000.0, $result['total_input_vat']);
        $this->assertEquals(8_000.0, $result['net_vat_payable']);
    }

    public function test_calculate_refund_when_input_exceeds_output(): void
    {
        // Supplies 50k (output 5k), purchases 200k (input 20k) → refund −15k
        $result = $this->service->calculate(50_000.0, 0, 0, 200_000.0);

        $this->assertEquals(-15_000.0, $result['net_vat_payable']);
    }

    public function test_calculate_zero_rated_and_exempt_supplies_included(): void
    {
        $result = $this->service->calculate(100_000.0, 20_000.0, 10_000.0);

        $this->assertEquals(20_000.0, $result['zero_rated_supplies']);
        $this->assertEquals(10_000.0, $result['exempt_supplies']);
        // Zero-rated and exempt don't generate output VAT
        $this->assertEquals(10_000.0, $result['output_vat']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // createReturn()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_create_return_persists_to_database(): void
    {
        $return = $this->service->createReturn([
            'organization_id'         => $this->organization->id,
            'period_year'             => 2025,
            'period_quarter'          => 1,
            'standard_rated_supplies' => 100_000.0,
        ], $this->user->id);

        $this->assertDatabaseHas('bahrain_vat_returns', [
            'id'              => $return->id,
            'organization_id' => $this->organization->id,
            'period_year'     => 2025,
            'period_quarter'  => 1,
            'status'          => 'draft',
        ]);
    }

    public function test_create_return_computes_q1_period_dates(): void
    {
        $return = $this->service->createReturn([
            'organization_id' => $this->organization->id,
            'period_year'     => 2025,
            'period_quarter'  => 1,
        ], $this->user->id);

        $this->assertEquals('2025-01-01', $return->period_start->format('Y-m-d'));
        $this->assertEquals('2025-03-31', $return->period_end->format('Y-m-d'));
    }

    public function test_create_return_q1_filing_due_is_april_30(): void
    {
        $return = $this->service->createReturn([
            'organization_id' => $this->organization->id,
            'period_year'     => 2025,
            'period_quarter'  => 1,
        ], $this->user->id);

        $this->assertEquals('2025-04-30', $return->filing_due_date->format('Y-m-d'));
    }

    public function test_create_return_q4_filing_due_is_jan_31_following_year(): void
    {
        $return = $this->service->createReturn([
            'organization_id' => $this->organization->id,
            'period_year'     => 2025,
            'period_quarter'  => 4,
        ], $this->user->id);

        $this->assertEquals('2026-01-31', $return->filing_due_date->format('Y-m-d'));
    }

    public function test_create_return_updates_existing_draft(): void
    {
        $first = $this->service->createReturn([
            'organization_id'         => $this->organization->id,
            'period_year'             => 2025,
            'period_quarter'          => 2,
            'standard_rated_supplies' => 50_000.0,
        ], $this->user->id);

        $second = $this->service->createReturn([
            'organization_id'         => $this->organization->id,
            'period_year'             => 2025,
            'period_quarter'          => 2,
            'standard_rated_supplies' => 60_000.0,
        ], $this->user->id);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(60_000.0, $second->standard_rated_supplies);
    }

    public function test_create_return_throws_if_already_submitted(): void
    {
        BahrainVatReturn::factory()->create([
            'organization_id' => $this->organization->id,
            'period_year'     => 2025,
            'period_quarter'  => 3,
            'period_month'    => null,
            'status'          => BahrainVatReturn::STATUS_SUBMITTED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("status 'submitted'");

        $this->service->createReturn([
            'organization_id' => $this->organization->id,
            'period_year'     => 2025,
            'period_quarter'  => 3,
        ], $this->user->id);
    }

    public function test_create_return_throws_for_invalid_quarter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createReturn([
            'organization_id' => $this->organization->id,
            'period_year'     => 2025,
            'period_quarter'  => 5,
        ], $this->user->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // submitReturn()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_submit_transitions_draft_to_submitted(): void
    {
        $return = BahrainVatReturn::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => BahrainVatReturn::STATUS_DRAFT,
        ]);

        $submitted = $this->service->submitReturn($return, 'NBR-2025-Q1-001');

        $this->assertEquals(BahrainVatReturn::STATUS_SUBMITTED, $submitted->status);
        $this->assertEquals('NBR-2025-Q1-001', $submitted->nbr_reference);
        $this->assertNotNull($submitted->filed_at);
    }

    public function test_submit_throws_if_not_draft(): void
    {
        $return = BahrainVatReturn::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => BahrainVatReturn::STATUS_SUBMITTED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitReturn($return);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // exportCsv()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_export_csv_contains_all_8_boxes(): void
    {
        $return = BahrainVatReturn::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $csv = $this->service->exportCsv($return);

        for ($box = 1; $box <= 8; $box++) {
            $this->assertStringContainsString("\"$box\"", $csv);
        }
    }

    public function test_export_csv_first_line_is_header(): void
    {
        $return = BahrainVatReturn::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $csv   = $this->service->exportCsv($return);
        $lines = explode("\n", trim($csv));

        $this->assertStringContainsString('Box', $lines[0]);
        $this->assertStringContainsString('Description', $lines[0]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Model helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function test_is_refund_returns_true_when_net_negative(): void
    {
        $return = BahrainVatReturn::factory()->refund()->make([
            'organization_id' => $this->organization->id,
        ]);

        $this->assertTrue($return->isRefund());
    }

    public function test_is_refund_returns_false_when_net_positive(): void
    {
        $return = BahrainVatReturn::factory()->make([
            'organization_id' => $this->organization->id,
            'net_vat_payable' => 5_000.0,
        ]);

        $this->assertFalse($return->isRefund());
    }
}
