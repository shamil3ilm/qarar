<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentAllocation;
use App\Models\Sales\PaymentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PaymentReceivedTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/sales/payments-received';
    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.payments.view',
            'sales.payments.create',
            'sales.payments.delete',
            'sales.payments.complete',
            'sales.payments.void',
            'sales.payments.allocate',
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | List Payments
    |--------------------------------------------------------------------------
    */

    public function test_can_list_payments(): void
    {
        PaymentReceived::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
    }

    public function test_list_payments_returns_only_own_organization(): void
    {
        PaymentReceived::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
        ]);

        $otherOrg = Organization::factory()->create();
        $otherCustomer = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);
        PaymentReceived::factory()->count(3)->create([
            'organization_id' => $otherOrg->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $payment) {
            $this->assertEquals($this->organization->id, $payment['organization_id']);
        }
    }

    public function test_list_payments_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1{$this->baseUrl}", [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_can_filter_payments_by_customer(): void
    {
        PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}?customer_id={$this->customer->id}");

        $this->assertPaginatedResponse($response);
    }

    public function test_can_filter_payments_by_status(): void
    {
        PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => PaymentReceived::STATUS_PENDING,
        ]);
        PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => PaymentReceived::STATUS_COMPLETED,
        ]);

        $response = $this->apiGet("{$this->baseUrl}?status=pending");

        $this->assertPaginatedResponse($response);
    }

    public function test_can_filter_payments_by_payment_method(): void
    {
        PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'payment_method' => PaymentReceived::METHOD_CASH,
        ]);

        $response = $this->apiGet("{$this->baseUrl}?payment_method=cash");

        $this->assertPaginatedResponse($response);
    }

    public function test_can_filter_payments_by_date_range(): void
    {
        PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'payment_date' => '2025-02-15',
        ]);

        $response = $this->apiGet("{$this->baseUrl}?start_date=2025-02-01&end_date=2025-02-28");

        $this->assertPaginatedResponse($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Payment
    |--------------------------------------------------------------------------
    */

    public function test_can_create_payment(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
            'total' => 5000.00,
            'amount_due' => 5000.00,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentReceived::METHOD_BANK_TRANSFER,
            'amount' => 5000.00,
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0000,
            'reference' => 'TRF-2025-001',
            'notes' => 'Full payment for invoice',
            'allocations' => [
                [
                    'invoice_id' => $invoice->id,
                    'amount' => 5000.00,
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $data = $response->json('data');
        $this->assertEquals($this->customer->id, $data['customer_id']);
        $this->assertNotNull($data['payment_number']);
    }

    public function test_can_create_payment_with_multiple_allocations(): void
    {
        $invoice1 = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
            'total' => 3000.00,
            'amount_due' => 3000.00,
        ]);

        $invoice2 = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
            'total' => 2000.00,
            'amount_due' => 2000.00,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentReceived::METHOD_CASH,
            'amount' => 5000.00,
            'currency_code' => 'SAR',
            'allocations' => [
                [
                    'invoice_id' => $invoice1->id,
                    'amount' => 3000.00,
                ],
                [
                    'invoice_id' => $invoice2->id,
                    'amount' => 2000.00,
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
    }

    public function test_can_create_partial_payment(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
            'total' => 10000.00,
            'amount_due' => 10000.00,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentReceived::METHOD_CASH,
            'amount' => 4000.00,
            'currency_code' => 'SAR',
            'allocations' => [
                [
                    'invoice_id' => $invoice->id,
                    'amount' => 4000.00,
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
    }

    public function test_can_create_overpayment(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
            'total' => 1000.00,
            'amount_due' => 1000.00,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentReceived::METHOD_BANK_TRANSFER,
            'amount' => 1500.00,
            'currency_code' => 'SAR',
            'allocations' => [
                [
                    'invoice_id' => $invoice->id,
                    'amount' => 1000.00,
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        // Overpayment should be handled - unallocated amount remains
        $this->assertCreatedResponse($response);
    }

    public function test_create_payment_requires_authentication(): void
    {
        $response = $this->postJson("/api/v1{$this->baseUrl}", [
            'customer_id' => $this->customer->id,
            'amount' => 100.00,
        ], ['Accept' => 'application/json']);

        $this->assertUnauthorized($response);
    }

    public function test_create_payment_validates_required_fields(): void
    {
        $response = $this->apiPost($this->baseUrl, []);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_payment_validates_customer_exists(): void
    {
        $payload = [
            'customer_id' => 99999,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentReceived::METHOD_CASH,
            'amount' => 1000.00,
            'currency_code' => 'SAR',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_payment_validates_positive_amount(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentReceived::METHOD_CASH,
            'amount' => -100.00,
            'currency_code' => 'SAR',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_payment_validates_payment_method(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'invalid_method',
            'amount' => 1000.00,
            'currency_code' => 'SAR',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_payment_validates_allocation_amount_not_exceeding_invoice_due(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
            'total' => 1000.00,
            'amount_due' => 1000.00,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentReceived::METHOD_CASH,
            'amount' => 2000.00,
            'currency_code' => 'SAR',
            'allocations' => [
                [
                    'invoice_id' => $invoice->id,
                    'amount' => 2000.00, // Exceeds invoice amount_due
                ],
            ],
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
        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$payment->id}");

        $this->assertSuccessResponse($response);
        $this->assertEquals($payment->id, $response->json('data.id'));
    }

    public function test_cannot_show_payment_from_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherCustomer = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);
        $payment = PaymentReceived::factory()->create([
            'organization_id' => $otherOrg->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$payment->id}");

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Complete Payment
    |--------------------------------------------------------------------------
    */

    public function test_can_complete_pending_payment(): void
    {
        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => PaymentReceived::STATUS_PENDING,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$payment->id}/complete");

        $this->assertSuccessResponse($response);
        $this->assertEquals(PaymentReceived::STATUS_COMPLETED, $response->json('data.status'));
    }

    public function test_cannot_complete_voided_payment(): void
    {
        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => PaymentReceived::STATUS_VOIDED,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$payment->id}/complete");

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Void Payment
    |--------------------------------------------------------------------------
    */

    public function test_can_void_pending_payment(): void
    {
        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => PaymentReceived::STATUS_PENDING,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$payment->id}/void", [
            'reason' => 'Customer requested cancellation',
        ]);

        $this->assertSuccessResponse($response);
        $this->assertEquals(PaymentReceived::STATUS_VOIDED, $response->json('data.status'));
    }

    /*
    |--------------------------------------------------------------------------
    | Bounce Payment
    |--------------------------------------------------------------------------
    */

    public function test_can_mark_payment_as_bounced(): void
    {
        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => PaymentReceived::STATUS_COMPLETED,
            'payment_method' => PaymentReceived::METHOD_CHEQUE,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$payment->id}/bounce", [
            'reason' => 'Insufficient funds',
        ]);

        $this->assertSuccessResponse($response);
        $this->assertEquals(PaymentReceived::STATUS_BOUNCED, $response->json('data.status'));
    }

    /*
    |--------------------------------------------------------------------------
    | Allocate Payment
    |--------------------------------------------------------------------------
    */

    public function test_can_allocate_payment_to_invoice(): void
    {
        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => PaymentReceived::STATUS_COMPLETED,
            'amount' => 5000.00,
        ]);

        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
            'total' => 5000.00,
            'amount_due' => 5000.00,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$payment->id}/allocate", [
            'allocations' => [
                [
                    'invoice_id' => $invoice->id,
                    'amount' => 5000.00,
                ],
            ],
        ]);

        $this->assertSuccessResponse($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Payment
    |--------------------------------------------------------------------------
    */

    public function test_can_delete_pending_payment(): void
    {
        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => PaymentReceived::STATUS_PENDING,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$payment->id}");

        $this->assertSuccessResponse($response);
    }

    public function test_cannot_delete_completed_payment(): void
    {
        $payment = PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => PaymentReceived::STATUS_COMPLETED,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$payment->id}");

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Summary
    |--------------------------------------------------------------------------
    */

    public function test_can_get_payment_summary(): void
    {
        PaymentReceived::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => PaymentReceived::STATUS_COMPLETED,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/summary");

        $this->assertSuccessResponse($response);
    }
}
