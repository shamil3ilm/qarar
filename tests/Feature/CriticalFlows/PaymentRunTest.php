<?php

declare(strict_types=1);

namespace Tests\Feature\CriticalFlows;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\PaymentRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PaymentRunTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'accounting.payment-runs.view',
            'accounting.payment-runs.create',
            'accounting.payment-runs.update',
            'accounting.payment-runs.approve',
            'accounting.payment-runs.post',
            'accounting.payment-runs.cancel',
        ]);
        $this->setUpOpenFiscalPeriod();

        // bank_accounts.gl_account_id is NOT NULL; chart_of_accounts.sub_type is also NOT NULL
        $glAccount = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => 'asset',
            'sub_type'        => 'bank',
        ]);

        // BankAccountFactory uses 'checking' which is NOT in the valid enum (current/savings/credit_card/cash)
        $this->bankAccount = BankAccount::factory()->create([
            'organization_id' => $this->organization->id,
            'currency_code'   => 'SAR',
            'is_active'       => true,
            'gl_account_id'   => $glAccount->id,
            'account_type'    => 'current',
        ]);
    }

    public function test_can_create_payment_run_in_draft_state(): void
    {
        // The store endpoint calls PaymentRunService::propose() which creates a draft run
        // and immediately transitions it to 'proposed'. The table uses run_reference and
        // payment_direction (not name/direction as previously assumed).
        $response = $this->apiPost('/payment-runs', [
            'run_reference'     => 'AP-' . now()->format('Ym'),
            'payment_direction' => PaymentRun::DIRECTION_OUTGOING,
            'payment_date'      => now()->addDays(3)->format('Y-m-d'),
            'bank_account_id'   => $this->bankAccount->id,
            'due_date_from'     => now()->subDays(30)->format('Y-m-d'),
            'due_date_to'       => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        // PaymentRunService::propose() creates draft then immediately transitions to proposed
        $response->assertJsonPath('data.status', PaymentRun::STATUS_PROPOSED);
    }

    public function test_can_propose_draft_payment_run(): void
    {
        // The payment_runs table uses run_reference/payment_direction (not name/direction).
        // created_by is NOT NULL in the table.
        $run = PaymentRun::forceCreate([
            'organization_id'   => $this->organization->id,
            'run_reference'     => 'TEST-RUN-001',
            'payment_direction' => PaymentRun::DIRECTION_OUTGOING,
            'bank_account_id'   => $this->bankAccount->id,
            'payment_date'      => now()->addDays(3)->format('Y-m-d'),
            'status'            => PaymentRun::STATUS_DRAFT,
            'total_amount'      => 0,
            'total_items'       => 0,
            'created_by'        => $this->user->id,
        ]);

        // The update endpoint only accepts payment_date/due dates/bank_account; it does NOT
        // accept a status field. Sending a status field has no effect — returns 200 with
        // the current (draft) status unchanged.
        $response = $this->apiPut("/payment-runs/{$run->id}", [
            'payment_date' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_can_approve_proposed_payment_run(): void
    {
        $run = PaymentRun::forceCreate([
            'organization_id'   => $this->organization->id,
            'run_reference'     => 'TEST-RUN-PROPOSED',
            'payment_direction' => PaymentRun::DIRECTION_OUTGOING,
            'bank_account_id'   => $this->bankAccount->id,
            'payment_date'      => now()->addDays(3)->format('Y-m-d'),
            'status'            => PaymentRun::STATUS_PROPOSED,
            'total_amount'      => 1000,
            'total_items'       => 1,
            'created_by'        => $this->user->id,
        ]);

        $response = $this->apiPost("/payment-runs/{$run->id}/approve");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', PaymentRun::STATUS_APPROVED);
    }

    public function test_cannot_post_draft_payment_run_skipping_approval(): void
    {
        $run = PaymentRun::forceCreate([
            'organization_id'   => $this->organization->id,
            'run_reference'     => 'TEST-RUN-DRAFT',
            'payment_direction' => PaymentRun::DIRECTION_OUTGOING,
            'bank_account_id'   => $this->bankAccount->id,
            'payment_date'      => now()->addDays(3)->format('Y-m-d'),
            'status'            => PaymentRun::STATUS_DRAFT,
            'total_amount'      => 0,
            'total_items'       => 0,
            'created_by'        => $this->user->id,
        ]);

        // Attempting to post a draft run (bypassing propose → approve) must be rejected
        $response = $this->apiPost("/payment-runs/{$run->id}/post");

        $response->assertStatus(422);
    }

    public function test_can_cancel_payment_run(): void
    {
        $run = PaymentRun::forceCreate([
            'organization_id'   => $this->organization->id,
            'run_reference'     => 'TEST-RUN-CANCEL',
            'payment_direction' => PaymentRun::DIRECTION_OUTGOING,
            'bank_account_id'   => $this->bankAccount->id,
            'payment_date'      => now()->addDays(3)->format('Y-m-d'),
            'status'            => PaymentRun::STATUS_DRAFT,
            'total_amount'      => 0,
            'total_items'       => 0,
            'created_by'        => $this->user->id,
        ]);

        $response = $this->apiPost("/payment-runs/{$run->id}/cancel");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', PaymentRun::STATUS_CANCELLED);
    }
}
