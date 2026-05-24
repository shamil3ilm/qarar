<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\ZakatAssessment;
use App\Services\Accounting\ZakatCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ZakatCalculationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private ZakatCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
        $this->service = app(ZakatCalculationService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Zakat rate constant
    // ─────────────────────────────────────────────────────────────────────────

    public function test_zakat_rate_is_2_5_percent(): void
    {
        $this->assertEquals(2.5, ZakatCalculationService::ZAKAT_RATE_PCT);
    }

    public function test_zakat_assessment_model_rate_constant_is_2_5(): void
    {
        $this->assertEquals(2.5, ZakatAssessment::ZAKAT_RATE);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Base calculation (explicit overrides avoid journal entry setup)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_calculate_base_returns_correct_zakat_due(): void
    {
        // Assets 1,000,000 − liabilities 200,000 − non-zakatable 100,000 = base 700,000
        // Zakat = 700,000 × 2.5% = 17,500
        $result = $this->service->calculateBase(
            $this->organization->id,
            100.0,
            1_000_000.0,   // total assets
            200_000.0,     // total liabilities
            100_000.0      // non-zakatable
        );

        $this->assertEquals(1_000_000.0, $result['total_assets']);
        $this->assertEquals(200_000.0, $result['total_liabilities']);
        $this->assertEquals(100_000.0, $result['non_zakatable_assets']);
        $this->assertEquals(700_000.0, $result['zakat_base']);
        $this->assertEquals(17_500.0, $result['zakat_due']);
    }

    public function test_zakat_base_is_non_negative_when_liabilities_exceed_assets(): void
    {
        $result = $this->service->calculateBase(
            $this->organization->id,
            100.0,
            50_000.0,    // assets
            200_000.0,   // liabilities exceed
            0.0
        );

        $this->assertEquals(0.0, $result['zakat_base']);
        $this->assertEquals(0.0, $result['zakat_due']);
    }

    public function test_zakat_applies_saudi_ownership_proportion(): void
    {
        // Base = 1,000,000 at 50% Saudi ownership → due = 1,000,000 × 2.5% × 50% = 12,500
        $result = $this->service->calculateBase(
            $this->organization->id,
            50.0,          // Saudi ownership
            1_000_000.0,
            0.0,
            0.0
        );

        $this->assertEquals(50.0, $result['saudi_ownership_pct']);
        $this->assertEquals(12_500.0, $result['zakat_due']);
    }

    public function test_validate_ownership_pct_bounds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->calculateBase($this->organization->id, 110.0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // createAssessment
    // ─────────────────────────────────────────────────────────────────────────

    public function test_create_assessment_persists_to_database(): void
    {
        $assessment = $this->service->createAssessment([
            'organization_id'      => $this->organization->id,
            'assessment_year'      => 2025,
            'total_assets'         => 500_000.0,
            'total_liabilities'    => 100_000.0,
            'non_zakatable_assets' => 50_000.0,
        ], $this->user->id);

        $this->assertDatabaseHas('zakat_assessments', [
            'id'              => $assessment->id,
            'organization_id' => $this->organization->id,
            'assessment_year' => 2025,
            'status'          => ZakatAssessment::STATUS_DRAFT,
        ]);
    }

    public function test_create_assessment_computes_filing_due_date(): void
    {
        $assessment = $this->service->createAssessment([
            'organization_id' => $this->organization->id,
            'assessment_year' => 2025,
        ], $this->user->id);

        $this->assertEquals('2026-04-30', $assessment->filing_due_date->format('Y-m-d'));
    }

    public function test_create_assessment_updates_existing_draft(): void
    {
        $first = $this->service->createAssessment([
            'organization_id'   => $this->organization->id,
            'assessment_year'   => 2025,
            'total_assets'      => 400_000.0,
            'total_liabilities' => 0.0,
        ], $this->user->id);

        $second = $this->service->createAssessment([
            'organization_id'   => $this->organization->id,
            'assessment_year'   => 2025,
            'total_assets'      => 400_000.0,
            'total_liabilities' => 0.0,
        ], $this->user->id);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, ZakatAssessment::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('assessment_year', 2025)
            ->count());
    }

    public function test_create_assessment_throws_if_already_submitted(): void
    {
        ZakatAssessment::factory()->create([
            'organization_id' => $this->organization->id,
            'assessment_year' => 2025,
            'status'          => ZakatAssessment::STATUS_SUBMITTED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("status 'submitted'");

        $this->service->createAssessment([
            'organization_id' => $this->organization->id,
            'assessment_year' => 2025,
        ], $this->user->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // submitAssessment
    // ─────────────────────────────────────────────────────────────────────────

    public function test_submit_transitions_draft_to_submitted(): void
    {
        $assessment = ZakatAssessment::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => ZakatAssessment::STATUS_DRAFT,
        ]);

        $submitted = $this->service->submitAssessment($assessment, 'GAZT-2025-001');

        $this->assertEquals(ZakatAssessment::STATUS_SUBMITTED, $submitted->status);
        $this->assertEquals('GAZT-2025-001', $submitted->gazt_reference);
        $this->assertNotNull($submitted->filed_at);
    }

    public function test_submit_throws_if_not_draft(): void
    {
        $assessment = ZakatAssessment::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => ZakatAssessment::STATUS_SUBMITTED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitAssessment($assessment);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // recordPayment
    // ─────────────────────────────────────────────────────────────────────────

    public function test_record_payment_reduces_remaining_balance(): void
    {
        $assessment = ZakatAssessment::factory()->create([
            'organization_id' => $this->organization->id,
            'zakat_due'       => 10_000.0,
            'zakat_paid'      => 0.0,
            'zakat_remaining' => 10_000.0,
            'status'          => ZakatAssessment::STATUS_SUBMITTED,
        ]);

        $updated = $this->service->recordPayment($assessment, 4_000.0);

        $this->assertEquals('4000.0000', $updated->zakat_paid);
        $this->assertEquals('6000.0000', $updated->zakat_remaining);
    }

    public function test_record_full_payment_sets_status_to_paid(): void
    {
        $assessment = ZakatAssessment::factory()->create([
            'organization_id' => $this->organization->id,
            'zakat_due'       => 5_000.0,
            'zakat_paid'      => 0.0,
            'zakat_remaining' => 5_000.0,
            'status'          => ZakatAssessment::STATUS_SUBMITTED,
        ]);

        $updated = $this->service->recordPayment($assessment, 5_000.0);

        $this->assertEquals(ZakatAssessment::STATUS_PAID, $updated->status);
    }

    public function test_record_payment_throws_if_exceeds_outstanding(): void
    {
        $assessment = ZakatAssessment::factory()->create([
            'organization_id' => $this->organization->id,
            'zakat_due'       => 3_000.0,
            'zakat_paid'      => 1_000.0,
            'zakat_remaining' => 2_000.0,
            'status'          => ZakatAssessment::STATUS_SUBMITTED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds outstanding');

        $this->service->recordPayment($assessment, 5_000.0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Model helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function test_outstanding_balance_returns_correct_value(): void
    {
        $assessment = ZakatAssessment::factory()->make([
            'zakat_due'  => 10_000.0,
            'zakat_paid' => 3_000.0,
        ]);

        $this->assertEquals(7_000.0, $assessment->outstandingBalance());
    }
}
