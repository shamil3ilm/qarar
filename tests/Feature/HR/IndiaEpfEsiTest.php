<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\HR\EpfContribution;
use App\Services\HR\IndiaEpfEsiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class IndiaEpfEsiTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private IndiaEpfEsiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('IN');
        $this->service = app(IndiaEpfEsiService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EPF Calculation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_epf_employee_contribution_is_12_percent_of_pf_wage(): void
    {
        $result = $this->service->calculateEpf(basicSalary: 20000.00);

        // PF wage is capped at 15000; 12% = 1800
        $this->assertEquals('15000.00', $result['pf_wage']);
        $this->assertEquals('1800.00', $result['employee_contribution']);
    }

    public function test_epf_employer_contribution_mirrors_employee_at_12_percent(): void
    {
        $result = $this->service->calculateEpf(basicSalary: 10000.00);

        // 12% of 10000 = 1200
        $this->assertEquals('1200.00', $result['employer_contribution']);
    }

    public function test_epf_eps_is_calculated_correctly(): void
    {
        // 8.33% × 15000 = 1249.50 — below the ₹1250 statutory cap, so not rounded up
        $result = $this->service->calculateEpf(basicSalary: 20000.00);

        $this->assertEquals('1249.50', $result['employer_eps_contribution']);
    }

    public function test_epf_employer_diff_is_remainder_after_eps(): void
    {
        $result = $this->service->calculateEpf(basicSalary: 20000.00);

        // employee_contribution(1800) − EPS(1249.50) = 550.50
        $this->assertEquals('550.50', $result['employer_epf_contribution']);
    }

    public function test_epf_edli_is_half_percent_of_pf_wage(): void
    {
        $result = $this->service->calculateEpf(basicSalary: 10000.00);

        // 0.5% of 10000 = 50
        $this->assertEquals('50.00', $result['edli_contribution']);
    }

    public function test_epf_pf_wage_is_actual_salary_when_below_ceiling(): void
    {
        $result = $this->service->calculateEpf(basicSalary: 8000.00);

        $this->assertEquals('8000.00', $result['pf_wage']);
        $this->assertEquals('960.00', $result['employee_contribution']); // 12% × 8000
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ESI Calculation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_esi_employee_contribution_is_0_75_percent_of_gross(): void
    {
        $result = $this->service->calculateEsi(grossSalary: 18000.00);

        $this->assertEquals('135.00', $result['employee_contribution']); // 0.75% × 18000
        $this->assertTrue($result['is_applicable']);
    }

    public function test_esi_employer_contribution_is_3_25_percent_of_gross(): void
    {
        $result = $this->service->calculateEsi(grossSalary: 18000.00);

        $this->assertEquals('585.00', $result['employer_contribution']); // 3.25% × 18000
    }

    public function test_esi_not_applicable_above_21000_gross(): void
    {
        $result = $this->service->calculateEsi(grossSalary: 21001.00);

        $this->assertFalse($result['is_applicable']);
        $this->assertEquals('0.00', $result['employee_contribution']);
        $this->assertEquals('0.00', $result['employer_contribution']);
    }

    public function test_esi_is_applicable_at_exactly_21000(): void
    {
        $result = $this->service->calculateEsi(grossSalary: 21000.00);

        $this->assertTrue($result['is_applicable']);
        $this->assertEquals('157.50', $result['employee_contribution']); // 0.75% × 21000
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Professional Tax
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pt_karnataka_above_15000_is_200(): void
    {
        $this->assertEquals('200.00', $this->service->calculatePt(20000.00, 'KA'));
    }

    public function test_pt_karnataka_below_15000_is_zero(): void
    {
        $this->assertEquals('0.00', $this->service->calculatePt(14999.00, 'KA'));
    }

    public function test_pt_maharashtra_between_7500_and_9999(): void
    {
        $this->assertEquals('175.00', $this->service->calculatePt(8000.00, 'MH'));
    }

    public function test_pt_returns_zero_for_unknown_state(): void
    {
        $this->assertEquals('0.00', $this->service->calculatePt(20000.00, 'ZZ'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ECR File Generation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_ecr_file_contains_header_separator(): void
    {
        $period = \App\Models\HR\PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $ecr = $this->service->generateEcr($period);

        $this->assertStringStartsWith('#~#', $ecr);
    }

    public function test_ecr_file_contains_uan_and_contribution_data(): void
    {
        $period = \App\Models\HR\PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $employee = \App\Models\HR\Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
        ]);

        EpfContribution::create([
            'organization_id'           => $this->organization->id,
            'employee_id'               => $employee->id,
            'payroll_period_id'         => $period->id,
            'uan'                       => 'UAN100123456',
            'pf_wage'                   => '10000.00',
            'employee_contribution'     => '1200.00',
            'employer_epf_contribution' => '367.00',
            'employer_eps_contribution' => '833.00',
            'edli_contribution'         => '50.00',
            'admin_charges'             => '50.00',
            'status'                    => 'draft',
        ]);

        $ecr = $this->service->generateEcr($period);

        $this->assertStringContainsString('UAN100123456', $ecr);
        $this->assertStringContainsString('1200', $ecr);
        $this->assertStringContainsString('#~#', $ecr);
    }

    public function test_ecr_file_has_correct_delimiter(): void
    {
        $period = \App\Models\HR\PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $employee = \App\Models\HR\Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
        ]);

        EpfContribution::create([
            'organization_id'           => $this->organization->id,
            'employee_id'               => $employee->id,
            'payroll_period_id'         => $period->id,
            'uan'                       => 'UAN999888777',
            'pf_wage'                   => '15000.00',
            'employee_contribution'     => '1800.00',
            'employer_epf_contribution' => '550.00',
            'employer_eps_contribution' => '1250.00',
            'edli_contribution'         => '75.00',
            'admin_charges'             => '75.00',
            'status'                    => 'draft',
        ]);

        $ecr = $this->service->generateEcr($period);
        $lines = explode("\r\n", trim($ecr));

        // First line is the header
        $this->assertEquals('#~#', $lines[0]);

        // Second line is the employee data, using #~# delimiter
        $this->assertStringContainsString('#~#', $lines[1]);
        $fields = explode('#~#', $lines[1]);
        $this->assertCount(11, $fields); // 11 fields per ECR spec
        $this->assertEquals('UAN999888777', $fields[0]);
    }
}
