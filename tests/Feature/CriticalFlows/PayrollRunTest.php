<?php

declare(strict_types=1);

namespace Tests\Feature\CriticalFlows;

use App\Models\HR\Employee;
use App\Models\HR\PayrollPeriod;
use App\Models\HR\Payslip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PayrollRunTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'hr.payroll.view',
            'hr.payroll.create',
            'hr.payroll.generate',
            'hr.payroll.approve',
            'hr.payroll.pay',
            'hr.payroll.close',
        ]);
        $this->setUpOpenFiscalPeriod();
    }

    public function test_can_create_payroll_period(): void
    {
        $startDate = now()->startOfMonth()->format('Y-m-d');
        $endDate   = now()->endOfMonth()->format('Y-m-d');

        $response = $this->apiPost('/hr/payroll/periods', [
            'name'         => 'Test Payroll ' . now()->format('F Y'),
            'start_date'   => $startDate,
            'end_date'     => $endDate,
            'payment_date' => now()->endOfMonth()->addDays(5)->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', PayrollPeriod::STATUS_OPEN);
    }

    public function test_can_retrieve_payroll_period(): void
    {
        $period = PayrollPeriod::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => PayrollPeriod::STATUS_OPEN,
        ]);

        $response = $this->apiGet("/hr/payroll/periods/{$period->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.id', $period->id);
        $response->assertJsonPath('data.status', PayrollPeriod::STATUS_OPEN);
    }

    public function test_can_close_processed_payroll_period(): void
    {
        $period = PayrollPeriod::factory()->processed()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->apiPost("/hr/payroll/periods/{$period->id}/close");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', PayrollPeriod::STATUS_CLOSED);
    }

    public function test_cannot_close_open_payroll_period_directly(): void
    {
        $period = PayrollPeriod::factory()->open()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->apiPost("/hr/payroll/periods/{$period->id}/close");

        // Closing an open period that has not been processed should return 4xx
        $response->assertStatus(422);
    }

    public function test_can_list_payslips_for_period(): void
    {
        $period = PayrollPeriod::factory()->open()->create([
            'organization_id' => $this->organization->id,
        ]);

        // payslips has a unique constraint on (payroll_period_id, employee_id),
        // so each payslip must belong to a different employee
        $employees = Employee::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
        ]);

        foreach ($employees as $employee) {
            Payslip::factory()->create([
                'organization_id'   => $this->organization->id,
                'payroll_period_id' => $period->id,
                'employee_id'       => $employee->id,
            ]);
        }

        $response = $this->apiGet('/hr/payroll/payslips');

        $response->assertStatus(200);
    }
}
