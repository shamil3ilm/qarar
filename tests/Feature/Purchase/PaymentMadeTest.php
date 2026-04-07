<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Accounting\BankAccount;
use App\Models\Purchase\Bill;
use App\Models\Purchase\BillPaymentAllocation;
use App\Models\Purchase\PaymentMade;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PaymentMadeTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/purchase/payments-made';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    /*
    |--------------------------------------------------------------------------
    | Unauthenticated Access
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_user_cannot_access_payments(): void
    {
        $response = $this->getJson("/api/v1{$this->baseUrl}");

        $this->assertUnauthorized($response);
    }

    public function test_unauthenticated_user_cannot_create_payment(): void
    {
        $response = $this->postJson("/api/v1{$this->baseUrl}", []);

        $this->assertUnauthorized($response);
    }

    /*
    |--------------------------------------------------------------------------
    | List Payments
    |--------------------------------------------------------------------------
    */

    public function test_can_list_payments(): void
    {
        $this->setUpAuthenticatedUser(['purchase.payments.view']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        PaymentMade::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
    }

    public function test_list_payments_respects_multi_tenant_isolation(): void
    {
        $this->setUpAuthenticatedUser(['purchase.payments.view']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        PaymentMade::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->user->id,
        ]);

        // Create payment in another organization
        $otherOrg = \App\Models\Core\Organization::factory()->create();
        $otherBranch = \App\Models\Core\Branch::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherSupplier = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);
        PaymentMade::factory()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'supplier_id' => $otherSupplier->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');
        foreach ($data as $payment) {
            $this->assertEquals($this->organization->id, $payment['organization_id']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create Payment
    |--------------------------------------------------------------------------
    */

    public function test_can_create_payment(): void
    {
        $this->setUpAuthenticatedUser(['purchase.payments.create']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 5000.00,
            'payment_method' => PaymentMade::METHOD_BANK_TRANSFER,
            'currency_code' => 'SAR',
            'reference' => 'TXN-2026-001',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $response->assertJsonPath('data.supplier_id', $supplier->id);
        $response->assertJsonPath('data.payment_method', PaymentMade::METHOD_BANK_TRANSFER);
    }

    public function test_can_create_payment_with_bill_allocations(): void
    {
        $this->setUpAuthenticatedUser(['purchase.payments.create']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $bill1 = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'status' => Bill::STATUS_APPROVED,
            'total' => 3000.00,
            'amount_due' => 3000.00,
            'amount_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        $bill2 = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'status' => Bill::STATUS_APPROVED,
            'total' => 2000.00,
            'amount_due' => 2000.00,
            'amount_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 5000.00,
            'payment_method' => PaymentMade::METHOD_BANK_TRANSFER,
            'currency_code' => 'SAR',
            'allocations' => [
                ['bill_id' => $bill1->id, 'amount' => 3000.00],
                ['bill_id' => $bill2->id, 'amount' => 2000.00],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
    }

    public function test_create_payment_validates_required_fields(): void
    {
        $this->setUpAuthenticatedUser(['purchase.payments.create']);

        $response = $this->apiPost($this->baseUrl, []);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['supplier_id', 'amount', 'payment_method']);
    }

    public function test_create_payment_validates_positive_amount(): void
    {
        $this->setUpAuthenticatedUser(['purchase.payments.create']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => -100.00,
            'payment_method' => PaymentMade::METHOD_CASH,
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_payment_validates_allocation_amount_not_exceeding_payment(): void
    {
        $this->setUpAuthenticatedUser(['purchase.payments.create']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'status' => Bill::STATUS_APPROVED,
            'total' => 5000.00,
            'amount_due' => 5000.00,
            'amount_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000.00,
            'payment_method' => PaymentMade::METHOD_BANK_TRANSFER,
            'allocations' => [
                ['bill_id' => $bill->id, 'amount' => 5000.00],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_payment_validates_valid_payment_method(): void
    {
        $this->setUpAuthenticatedUser(['purchase.payments.create']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000.00,
            'payment_method' => 'bitcoin',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Payment
    |--------------------------------------------------------------------------
    */

    public function test_can_show_payment(): void
    {
        $this->setUpAuthenticatedUser(['purchase.payments.view']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $payment = PaymentMade::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$payment->id}");

        $this->assertSuccessResponse($response);
        $response->assertJsonPath('data.id', $payment->id);
    }

    public function test_cannot_show_payment_from_another_organization(): void
    {
        $this->setUpAuthenticatedUser(['purchase.payments.view']);

        $otherOrg = \App\Models\Core\Organization::factory()->create();
        $otherBranch = \App\Models\Core\Branch::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherSupplier = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $payment = PaymentMade::factory()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'supplier_id' => $otherSupplier->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$payment->id}");

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Allocation to Bills
    |--------------------------------------------------------------------------
    */

    public function test_can_allocate_payment_to_bills(): void
    {
        $this->setUpAuthenticatedUser(['purchase.payments.allocate']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $payment = PaymentMade::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'amount' => 5000.00,
            'status' => PaymentMade::STATUS_COMPLETED,
            'created_by' => $this->user->id,
        ]);

        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'status' => Bill::STATUS_APPROVED,
            'total' => 3000.00,
            'amount_due' => 3000.00,
            'amount_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'allocations' => [
                ['bill_id' => $bill->id, 'amount' => 3000.00],
            ],
        ];

        $response = $this->apiPost("{$this->baseUrl}/{$payment->id}/allocate", $payload);

        $this->assertSuccessResponse($response);
    }

    public function test_cannot_allocate_more_than_payment_amount(): void
    {
        $this->setUpAuthenticatedUser(['purchase.payments.allocate']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $payment = PaymentMade::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'amount' => 1000.00,
            'status' => PaymentMade::STATUS_COMPLETED,
            'created_by' => $this->user->id,
        ]);

        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'status' => Bill::STATUS_APPROVED,
            'total' => 5000.00,
            'amount_due' => 5000.00,
            'amount_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'allocations' => [
                ['bill_id' => $bill->id, 'amount' => 5000.00],
            ],
        ];

        $response = $this->apiPost("{$this->baseUrl}/{$payment->id}/allocate", $payload);

        $this->assertErrorResponse($response, 422);
    }

    public function test_cannot_allocate_to_bill_from_different_supplier(): void
    {
        $this->setUpAuthenticatedUser(['purchase.payments.allocate']);

        $supplier1 = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $supplier2 = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $payment = PaymentMade::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier1->id,
            'amount' => 5000.00,
            'status' => PaymentMade::STATUS_COMPLETED,
            'created_by' => $this->user->id,
        ]);

        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier2->id,
            'status' => Bill::STATUS_APPROVED,
            'total' => 3000.00,
            'amount_due' => 3000.00,
            'amount_paid' => 0,
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'allocations' => [
                ['bill_id' => $bill->id, 'amount' => 3000.00],
            ],
        ];

        $response = $this->apiPost("{$this->baseUrl}/{$payment->id}/allocate", $payload);

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Permission Checks
    |--------------------------------------------------------------------------
    */

    public function test_user_without_permission_cannot_create_payment(): void
    {
        $this->setUpAuthenticatedUser([]);

        $response = $this->apiPost($this->baseUrl, [
            'supplier_id' => 1,
            'amount' => 1000.00,
            'payment_method' => 'cash',
        ]);

        $this->assertForbidden($response);
    }
}
