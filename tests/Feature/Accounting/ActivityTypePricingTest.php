<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\ActivityRate;
use App\Models\Accounting\ActivityType;
use App\Services\Accounting\ActivityTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ActivityTypePricingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private ActivityTypeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->service = app(ActivityTypeService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function seedRate(
        ActivityType $type,
        int $costCenterId,
        int $fiscalYearId,
        int $period,
        float $planned,
        float $actual = 0.0,
    ): ActivityRate {
        return $this->service->setRate($type, [
            'cost_center_id' => $costCenterId,
            'fiscal_year_id' => $fiscalYearId,
            'period'         => $period,
            'planned_rate'   => $planned,
            'actual_rate'    => $actual,
            'currency_code'  => 'SAR',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // setRate() — existing functionality (regression guard)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_set_rate_upserts_planned_and_actual(): void
    {
        $type       = ActivityType::factory()->create(['organization_id' => $this->organization->id]);
        $costCenter = \App\Models\Accounting\CostCenter::factory()->create(['organization_id' => $this->organization->id]);
        $fiscalYear = \App\Models\Accounting\FiscalYear::factory()->create(['organization_id' => $this->organization->id]);

        $rate = $this->seedRate($type, $costCenter->id, $fiscalYear->id, 1, 100.0, 95.0);

        $this->assertEquals(100.0, (float) $rate->planned_rate);
        $this->assertEquals(95.0, (float) $rate->actual_rate);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // confirmRate()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_confirm_rate_sets_is_confirmed_true(): void
    {
        $type       = ActivityType::factory()->create(['organization_id' => $this->organization->id]);
        $costCenter = \App\Models\Accounting\CostCenter::factory()->create(['organization_id' => $this->organization->id]);
        $fiscalYear = \App\Models\Accounting\FiscalYear::factory()->create(['organization_id' => $this->organization->id]);

        $this->seedRate($type, $costCenter->id, $fiscalYear->id, 3, 200.0, 210.0);

        $confirmed = $this->service->confirmRate($type, [
            'cost_center_id' => $costCenter->id,
            'fiscal_year_id' => $fiscalYear->id,
            'period'         => 3,
        ], $this->user->id);

        $this->assertTrue($confirmed->is_confirmed);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertEquals($this->user->id, $confirmed->confirmed_by);
    }

    public function test_confirm_rate_throws_if_already_confirmed(): void
    {
        $type       = ActivityType::factory()->create(['organization_id' => $this->organization->id]);
        $costCenter = \App\Models\Accounting\CostCenter::factory()->create(['organization_id' => $this->organization->id]);
        $fiscalYear = \App\Models\Accounting\FiscalYear::factory()->create(['organization_id' => $this->organization->id]);

        $this->seedRate($type, $costCenter->id, $fiscalYear->id, 4, 150.0);

        $params = [
            'cost_center_id' => $costCenter->id,
            'fiscal_year_id' => $fiscalYear->id,
            'period'         => 4,
        ];

        $this->service->confirmRate($type, $params, $this->user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already confirmed');

        $this->service->confirmRate($type, $params, $this->user->id);
    }

    public function test_confirm_rate_throws_for_invalid_period(): void
    {
        $type = ActivityType::factory()->create(['organization_id' => $this->organization->id]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->confirmRate($type, [
            'cost_center_id' => 1,
            'fiscal_year_id' => 1,
            'period'         => 13,
        ], $this->user->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // calculateVariance()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_variance_amount_positive_when_actual_exceeds_plan(): void
    {
        $type       = ActivityType::factory()->create(['organization_id' => $this->organization->id]);
        $costCenter = \App\Models\Accounting\CostCenter::factory()->create(['organization_id' => $this->organization->id]);
        $fiscalYear = \App\Models\Accounting\FiscalYear::factory()->create(['organization_id' => $this->organization->id]);

        $this->seedRate($type, $costCenter->id, $fiscalYear->id, 1, 100.0, 120.0);

        $variance = $this->service->calculateVariance($type, [
            'cost_center_id' => $costCenter->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);

        $this->assertEquals(20.0, $variance['periods'][0]['variance_amount']);
        $this->assertEquals(20.0, $variance['periods'][0]['variance_percent']);
    }

    public function test_variance_amount_negative_when_actual_below_plan(): void
    {
        $type       = ActivityType::factory()->create(['organization_id' => $this->organization->id]);
        $costCenter = \App\Models\Accounting\CostCenter::factory()->create(['organization_id' => $this->organization->id]);
        $fiscalYear = \App\Models\Accounting\FiscalYear::factory()->create(['organization_id' => $this->organization->id]);

        $this->seedRate($type, $costCenter->id, $fiscalYear->id, 2, 200.0, 180.0);

        $variance = $this->service->calculateVariance($type, [
            'cost_center_id' => $costCenter->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);

        $this->assertEquals(-20.0, $variance['periods'][0]['variance_amount']);
        $this->assertEquals(-10.0, $variance['periods'][0]['variance_percent']);
    }

    public function test_variance_totals_across_multiple_periods(): void
    {
        $type       = ActivityType::factory()->create(['organization_id' => $this->organization->id]);
        $costCenter = \App\Models\Accounting\CostCenter::factory()->create(['organization_id' => $this->organization->id]);
        $fiscalYear = \App\Models\Accounting\FiscalYear::factory()->create(['organization_id' => $this->organization->id]);

        $this->seedRate($type, $costCenter->id, $fiscalYear->id, 1, 100.0, 110.0);  // +10
        $this->seedRate($type, $costCenter->id, $fiscalYear->id, 2, 100.0, 90.0);   // -10

        $variance = $this->service->calculateVariance($type, [
            'cost_center_id' => $costCenter->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);

        $this->assertCount(2, $variance['periods']);
        $this->assertEquals(0.0, $variance['total_variance']);
    }

    public function test_variance_percent_zero_when_planned_rate_is_zero(): void
    {
        $type       = ActivityType::factory()->create(['organization_id' => $this->organization->id]);
        $costCenter = \App\Models\Accounting\CostCenter::factory()->create(['organization_id' => $this->organization->id]);
        $fiscalYear = \App\Models\Accounting\FiscalYear::factory()->create(['organization_id' => $this->organization->id]);

        $this->seedRate($type, $costCenter->id, $fiscalYear->id, 5, 0.0, 50.0);

        $variance = $this->service->calculateVariance($type, [
            'cost_center_id' => $costCenter->id,
            'fiscal_year_id' => $fiscalYear->id,
        ]);

        $this->assertEquals(0.0, $variance['periods'][0]['variance_percent']);
    }
}
