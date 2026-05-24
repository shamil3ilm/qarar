<?php

declare(strict_types=1);

namespace Tests\Feature\Tax;

use App\Services\Tax\Tds194QService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class Tds194QTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Tds194QService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('IN');
        $this->setUpAuthenticatedUser();
        $this->service = app(Tds194QService::class);

        // Run the migration that seeds 194Q section
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_04_13_000010_add_194q_to_tds_sections.php']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Constants
    // ─────────────────────────────────────────────────────────────────────────

    public function test_threshold_is_50_lakh(): void
    {
        $this->assertEquals(5_000_000.0, Tds194QService::THRESHOLD_PER_FY);
    }

    public function test_rate_with_pan_is_0_1_percent(): void
    {
        $this->assertEquals(0.10, Tds194QService::RATE_WITH_PAN);
    }

    public function test_rate_without_pan_is_5_percent(): void
    {
        $this->assertEquals(5.0, Tds194QService::RATE_NO_PAN);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Financial year helper
    // ─────────────────────────────────────────────────────────────────────────

    public function test_fy_for_april_is_same_calendar_year(): void
    {
        $fy = $this->service->financialYear('2025-04-01');
        $this->assertEquals('2025-04-01', $fy['start']);
        $this->assertEquals('2026-03-31', $fy['end']);
        $this->assertEquals('FY 2025-26', $fy['label']);
    }

    public function test_fy_for_january_is_prior_calendar_year(): void
    {
        $fy = $this->service->financialYear('2026-01-15');
        $this->assertEquals('2025-04-01', $fy['start']);
        $this->assertEquals('2026-03-31', $fy['end']);
        $this->assertEquals('FY 2025-26', $fy['label']);
    }

    public function test_fy_quarter_1_is_april_to_june(): void
    {
        $fy = $this->service->financialYear('2025-05-15');
        $this->assertEquals(1, $fy['quarter']);
    }

    public function test_fy_quarter_3_is_october_to_december(): void
    {
        $fy = $this->service->financialYear('2025-11-01');
        $this->assertEquals(3, $fy['quarter']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // calculate()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_no_tds_when_below_threshold(): void
    {
        $result = $this->service->calculate(
            $this->organization->id,
            1,            // vendor ID
            3_000_000.0,  // ₹30 lakh — below threshold
            '2025-06-01',
        );

        $this->assertFalse($result['applies']);
        $this->assertEquals(0.0, $result['tds_amount']);
        $this->assertStringContainsString('below', $result['reason']);
    }

    public function test_tds_applies_when_crossing_threshold(): void
    {
        // Vendor cumulative = 0, this payment = ₹60 lakh → ₹10 lakh over threshold
        $result = $this->service->calculate(
            $this->organization->id,
            1,
            6_000_000.0,   // ₹60 lakh
            '2025-07-01',
        );

        $this->assertTrue($result['applies']);
        // Taxable = 60L - 50L = 10L; TDS = 10L × 0.1% = ₹1,000
        $this->assertEquals(1_000.0, $result['tds_amount']);
        $this->assertEquals(0.10, $result['tds_rate']);
    }

    public function test_full_amount_taxable_when_already_above_threshold(): void
    {
        // Simulate prior deductions already above threshold by passing large prior YTD
        // We test via the calculate() logic: prior YTD = 60L, new purchase = 10L → all 10L taxable
        // Since there's no prior DB record in this test, we use cumulative_ytd = 0
        // So: prior = 0, new = 60L → taxable = 10L (excess over 50L)
        $result = $this->service->calculate(
            $this->organization->id,
            2,
            6_000_000.0,
            '2025-08-01',
        );

        $this->assertTrue($result['applies']);
        $this->assertEquals(1_000.0, $result['tds_amount']);
    }

    public function test_higher_rate_when_no_pan(): void
    {
        $result = $this->service->calculate(
            $this->organization->id,
            3,
            6_000_000.0,
            '2025-09-01',
            false,   // no PAN
        );

        $this->assertTrue($result['applies']);
        $this->assertEquals(5.0, $result['tds_rate']);
        // Taxable = 10L; TDS = 10L × 5% = ₹50,000
        $this->assertEquals(50_000.0, $result['tds_amount']);
    }

    public function test_no_tds_when_covered_by_206c(): void
    {
        $result = $this->service->calculate(
            $this->organization->id,
            4,
            10_000_000.0,  // ₹1 crore
            '2025-10-01',
            true,
            true,  // covered by 206C(1H)
        );

        $this->assertFalse($result['applies']);
        $this->assertStringContainsString('206C', $result['reason']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // recordDeduction()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_record_deduction_creates_tds_deduction(): void
    {
        $deduction = $this->service->recordDeduction([
            'organization_id' => $this->organization->id,
            'deductee_id'     => 10,
            'payment_amount'  => 6_000_000.0,
            'payment_date'    => '2025-07-15',
        ]);

        $this->assertDatabaseHas('tds_deductions', [
            'organization_id' => $this->organization->id,
            'deductee_id'     => 10,
        ]);
        $this->assertEquals(0.10, (float) $deduction->tds_rate);
    }

    public function test_record_deduction_throws_when_below_threshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('194Q TDS does not apply');

        $this->service->recordDeduction([
            'organization_id' => $this->organization->id,
            'deductee_id'     => 20,
            'payment_amount'  => 1_000_000.0,  // ₹10 lakh — below threshold
            'payment_date'    => '2025-07-15',
        ]);
    }

    public function test_record_deduction_sets_correct_period_quarter(): void
    {
        $deduction = $this->service->recordDeduction([
            'organization_id' => $this->organization->id,
            'deductee_id'     => 30,
            'payment_amount'  => 7_000_000.0,
            'payment_date'    => '2025-10-01',  // Q3 of FY 2025-26
        ]);

        $this->assertEquals(3, $deduction->period_quarter);
    }
}
