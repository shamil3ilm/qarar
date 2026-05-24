<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Models\Compliance\UaeCitAssessment;
use App\Services\Compliance\UaeCorporateTaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class UaeCorporateTaxTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private UaeCorporateTaxService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('AE');
        $this->setUpAuthenticatedUser();
        $this->service = app(UaeCorporateTaxService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Constants
    // ─────────────────────────────────────────────────────────────────────────

    public function test_cit_rate_constant_is_9_percent(): void
    {
        $this->assertEquals(9.0, UaeCorporateTaxService::CIT_RATE_PCT);
    }

    public function test_zero_rate_threshold_is_375000(): void
    {
        $this->assertEquals(375_000.0, UaeCorporateTaxService::ZERO_RATE_THRESHOLD);
    }

    public function test_small_business_threshold_is_3_million(): void
    {
        $this->assertEquals(3_000_000.0, UaeCorporateTaxService::SMALL_BIZ_THRESHOLD);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // calculate()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_income_below_threshold_results_in_zero_cit(): void
    {
        $result = $this->service->calculate(300_000.0);

        $this->assertEquals(300_000.0, $result['taxable_income']);
        $this->assertEquals(0.0, $result['cit_due']);
    }

    public function test_income_equal_to_threshold_results_in_zero_cit(): void
    {
        $result = $this->service->calculate(375_000.0);
        $this->assertEquals(0.0, $result['cit_due']);
    }

    public function test_income_above_threshold_applies_9_percent_on_excess(): void
    {
        // Taxable income 500,000 — excess = 125,000 — CIT = 11,250
        $result = $this->service->calculate(500_000.0);

        $this->assertEquals(500_000.0, $result['taxable_income']);
        $this->assertEquals(11_250.0, $result['cit_due']);
    }

    public function test_add_backs_increase_taxable_income(): void
    {
        // Accounting 300k + add_backs 100k = taxable 400k, excess 25k, CIT 2,250
        $result = $this->service->calculate(300_000.0, 100_000.0, 0.0);

        $this->assertEquals(400_000.0, $result['taxable_income']);
        $this->assertEquals(2_250.0, $result['cit_due']);
    }

    public function test_deductions_reduce_taxable_income(): void
    {
        // Accounting 500k − deductions 200k = taxable 300k → 0 CIT
        $result = $this->service->calculate(500_000.0, 0.0, 200_000.0);

        $this->assertEquals(300_000.0, $result['taxable_income']);
        $this->assertEquals(0.0, $result['cit_due']);
    }

    public function test_small_business_relief_zeroes_cit(): void
    {
        // Even with high income, SBR elected → CIT = 0
        $result = $this->service->calculate(2_000_000.0, 0.0, 0.0, true);

        $this->assertTrue($result['small_business_relief']);
        $this->assertEquals(0.0, $result['cit_due']);
    }

    public function test_taxable_income_cannot_be_negative(): void
    {
        $result = $this->service->calculate(100_000.0, 0.0, 500_000.0);
        $this->assertEquals(0.0, $result['taxable_income']);
        $this->assertEquals(0.0, $result['cit_due']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // createAssessment()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_create_assessment_persists_to_database(): void
    {
        $assessment = $this->service->createAssessment([
            'organization_id'  => $this->organization->id,
            'tax_year'         => 2025,
            'accounting_income' => 1_000_000.0,
        ], $this->user->id);

        $this->assertDatabaseHas('uae_cit_assessments', [
            'id'              => $assessment->id,
            'organization_id' => $this->organization->id,
            'tax_year'        => 2025,
            'status'          => 'draft',
        ]);
    }

    public function test_create_assessment_computes_filing_due_sep_30(): void
    {
        $assessment = $this->service->createAssessment([
            'organization_id'  => $this->organization->id,
            'tax_year'         => 2025,
            'accounting_income' => 500_000.0,
        ], $this->user->id);

        $this->assertEquals('2026-09-30', $assessment->filing_due_date->format('Y-m-d'));
    }

    public function test_create_assessment_updates_existing_draft(): void
    {
        $first = $this->service->createAssessment([
            'organization_id'  => $this->organization->id,
            'tax_year'         => 2025,
            'accounting_income' => 400_000.0,
        ], $this->user->id);

        $second = $this->service->createAssessment([
            'organization_id'  => $this->organization->id,
            'tax_year'         => 2025,
            'accounting_income' => 400_000.0,
        ], $this->user->id);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, UaeCitAssessment::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('tax_year', 2025)
            ->count());
    }

    public function test_create_assessment_throws_if_already_submitted(): void
    {
        UaeCitAssessment::factory()->create([
            'organization_id' => $this->organization->id,
            'tax_year'        => 2025,
            'status'          => UaeCitAssessment::STATUS_SUBMITTED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("status 'submitted'");

        $this->service->createAssessment([
            'organization_id'  => $this->organization->id,
            'tax_year'         => 2025,
            'accounting_income' => 500_000.0,
        ], $this->user->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // submitAssessment()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_submit_transitions_draft_to_submitted(): void
    {
        $assessment = UaeCitAssessment::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => UaeCitAssessment::STATUS_DRAFT,
        ]);

        $submitted = $this->service->submitAssessment($assessment, 'EMARA-2025-001');

        $this->assertEquals(UaeCitAssessment::STATUS_SUBMITTED, $submitted->status);
        $this->assertEquals('EMARA-2025-001', $submitted->emara_tax_reference);
        $this->assertNotNull($submitted->filed_at);
    }

    public function test_submit_throws_if_not_draft(): void
    {
        $assessment = UaeCitAssessment::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => UaeCitAssessment::STATUS_SUBMITTED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitAssessment($assessment);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // recordPayment()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_record_payment_reduces_remaining_balance(): void
    {
        $assessment = UaeCitAssessment::factory()->create([
            'organization_id' => $this->organization->id,
            'cit_due'         => 50_000.0,
            'cit_paid'        => 0.0,
            'cit_remaining'   => 50_000.0,
            'status'          => UaeCitAssessment::STATUS_SUBMITTED,
        ]);

        $updated = $this->service->recordPayment($assessment, 20_000.0);

        $this->assertEquals(20_000.0, $updated->cit_paid);
        $this->assertEquals(30_000.0, $updated->cit_remaining);
    }

    public function test_record_full_payment_sets_status_to_paid(): void
    {
        $assessment = UaeCitAssessment::factory()->create([
            'organization_id' => $this->organization->id,
            'cit_due'         => 10_000.0,
            'cit_paid'        => 0.0,
            'cit_remaining'   => 10_000.0,
            'status'          => UaeCitAssessment::STATUS_SUBMITTED,
        ]);

        $updated = $this->service->recordPayment($assessment, 10_000.0);

        $this->assertEquals(UaeCitAssessment::STATUS_PAID, $updated->status);
    }

    public function test_record_payment_throws_if_exceeds_outstanding(): void
    {
        $assessment = UaeCitAssessment::factory()->create([
            'organization_id' => $this->organization->id,
            'cit_due'         => 5_000.0,
            'cit_paid'        => 0.0,
            'cit_remaining'   => 5_000.0,
            'status'          => UaeCitAssessment::STATUS_SUBMITTED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds outstanding');

        $this->service->recordPayment($assessment, 10_000.0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Model helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function test_outstanding_balance_returns_correct_value(): void
    {
        $assessment = UaeCitAssessment::factory()->make([
            'cit_due'  => 20_000.0,
            'cit_paid' => 8_000.0,
        ]);

        $this->assertEquals(12_000.0, $assessment->outstandingBalance());
    }
}
