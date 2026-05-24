<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\InstallmentPlan;
use App\Models\Accounting\InstallmentSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class InstallmentPlanTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.installments.view',
            'accounting.installments.create',
            'accounting.installments.manage',
            'accounting.installments.post',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePlan(array $overrides = []): InstallmentPlan
    {
        return InstallmentPlan::create(array_merge([
            'organization_id'   => $this->organization->id,
            'document_type'     => 'invoice',
            'document_id'       => 1,
            'total_amount'      => 9000.00,
            'outstanding'       => 9000.00,
            'currency_code'     => 'SAR',
            'start_date'        => '2025-01-01',
            'installment_count' => 3,
            'status'            => 'draft',
            'created_by'        => $this->user->id,
        ], $overrides));
    }

    private function makeSchedule(InstallmentPlan $plan, array $overrides = []): InstallmentSchedule
    {
        return InstallmentSchedule::create(array_merge([
            'installment_plan_id' => $plan->id,
            'installment_number'  => 1,
            'due_date'            => '2025-02-01',
            'amount'              => 3000.00,
            'status'              => 'pending',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makePlan();
        $this->makePlan();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/installment-plans');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/installment-plans');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_equal_schedule_plan(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/installment-plans', [
                'document_type'     => 'invoice',
                'document_id'       => 1,
                'total_amount'      => 6000,
                'currency_code'     => 'SAR',
                'start_date'        => '2025-01-01',
                'installment_count' => 3,
                'frequency_days'    => 30,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/installment-plans', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_document_type_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/installment-plans', [
                'document_type'     => 'quote',
                'document_id'       => 1,
                'total_amount'      => 1000,
                'start_date'        => '2025-01-01',
                'installment_count' => 2,
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_plan_with_schedules(): void
    {
        $plan = $this->makePlan();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/installment-plans/' . $plan->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $plan->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/installment-plans/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Activate
    // -------------------------------------------------------------------------

    public function test_activate_transitions_draft_to_active(): void
    {
        $plan = $this->makePlan(['status' => 'draft']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/installment-plans/' . $plan->uuid . '/activate');

        $response->assertStatus(200);
        $this->assertEquals('active', $plan->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Cancel
    // -------------------------------------------------------------------------

    public function test_cancel_marks_plan_as_cancelled(): void
    {
        $plan = $this->makePlan(['status' => 'draft']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/installment-plans/' . $plan->uuid . '/cancel', [
                'reason' => 'Customer requested cancellation.',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $plan->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Record Payment
    // -------------------------------------------------------------------------

    public function test_record_payment_marks_schedule_as_paid(): void
    {
        $plan     = $this->makePlan(['status' => 'active']);
        $schedule = $this->makeSchedule($plan);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/installment-plans/' . $plan->uuid . '/schedules/' . $schedule->uuid . '/pay', [
                'paid_amount' => 3000.00,
                'paid_date'   => '2025-02-01',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('paid', $schedule->fresh()->status);
    }

    public function test_record_payment_validates_required_fields(): void
    {
        $plan     = $this->makePlan(['status' => 'active']);
        $schedule = $this->makeSchedule($plan);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/installment-plans/' . $plan->uuid . '/schedules/' . $schedule->uuid . '/pay', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Upcoming & Overdue
    // -------------------------------------------------------------------------

    public function test_upcoming_returns_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/installment-plans/upcoming?days=30');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_overdue_summary_returns_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/installment-plans/overdue-summary');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/installment-plans')->assertStatus(401);
    }
}
