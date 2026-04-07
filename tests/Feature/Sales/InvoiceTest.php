<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Accounting\Account;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class InvoiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/sales/invoices';
    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.invoices.view',
            'sales.invoices.create',
            'sales.invoices.edit',
            'sales.invoices.delete',
            'sales.invoices.send',
            'sales.invoices.void',
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | List Invoices
    |--------------------------------------------------------------------------
    */

    public function test_can_list_invoices(): void
    {
        Invoice::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
    }

    public function test_list_invoices_returns_only_own_organization(): void
    {
        Invoice::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
        ]);

        $otherOrg = Organization::factory()->create();
        $otherCustomer = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);
        Invoice::factory()->count(3)->create([
            'organization_id' => $otherOrg->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $invoice) {
            $this->assertEquals($this->organization->id, $invoice['organization_id']);
        }
    }

    public function test_list_invoices_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1{$this->baseUrl}", [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_can_filter_invoices_by_status(): void
    {
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
        ]);

        $response = $this->apiGet("{$this->baseUrl}?status=draft");

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');
        foreach ($data as $invoice) {
            $this->assertEquals(Invoice::STATUS_DRAFT, $invoice['status']);
        }
    }

    public function test_can_filter_invoices_by_customer(): void
    {
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
        ]);

        $otherCustomer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}?customer_id={$this->customer->id}");

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');
        foreach ($data as $invoice) {
            $this->assertEquals($this->customer->id, $invoice['customer_id']);
        }
    }

    public function test_can_filter_invoices_by_date_range(): void
    {
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'invoice_date' => '2025-01-15',
        ]);
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'invoice_date' => '2025-03-15',
        ]);

        $response = $this->apiGet("{$this->baseUrl}?start_date=2025-01-01&end_date=2025-01-31");

        $this->assertPaginatedResponse($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Invoice
    |--------------------------------------------------------------------------
    */

    public function test_can_create_draft_invoice(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0000,
            'notes' => 'Test invoice',
            'lines' => [
                [
                    'description' => 'Consulting Service',
                    'quantity' => 10,
                    'unit_price' => 100.00,
                    'tax_rate' => 15.00,
                ],
                [
                    'description' => 'Support Service',
                    'quantity' => 5,
                    'unit_price' => 200.00,
                    'tax_rate' => 15.00,
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $data = $response->json('data');
        $this->assertEquals(Invoice::STATUS_DRAFT, $data['status']);
        $this->assertEquals($this->customer->id, $data['customer_id']);
        $this->assertNotNull($data['invoice_number']);
    }

    public function test_invoice_number_is_auto_generated(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'tax_rate' => 15.00,
                ],
            ],
        ];

        $response1 = $this->apiPost($this->baseUrl, $payload);
        $response2 = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response1);
        $this->assertCreatedResponse($response2);

        $invoiceNumber1 = $response1->json('data.invoice_number');
        $invoiceNumber2 = $response2->json('data.invoice_number');

        $this->assertNotNull($invoiceNumber1);
        $this->assertNotNull($invoiceNumber2);
        $this->assertNotEquals($invoiceNumber1, $invoiceNumber2);
    }

    public function test_create_invoice_calculates_tax_vat_for_gcc(): void
    {
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.invoices.view',
            'sales.invoices.create',
        ]);

        $customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);

        $payload = [
            'customer_id' => $customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'description' => 'Product A',
                    'quantity' => 2,
                    'unit_price' => 1000.00,
                    'tax_rate' => 15.00, // VAT 15%
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $data = $response->json('data');
        // Subtotal: 2 * 1000 = 2000, Tax: 2000 * 0.15 = 300, Total: 2300
        $this->assertEquals('2000.0000', $data['subtotal']);
        $this->assertEquals('300.0000', $data['tax_amount']);
        $this->assertEquals('2300.0000', $data['total']);
    }

    public function test_create_invoice_calculates_tax_gst_for_india(): void
    {
        $this->setUpOrganization('IN');
        $this->setUpAuthenticatedUser([
            'sales.invoices.view',
            'sales.invoices.create',
        ]);

        $customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);

        $payload = [
            'customer_id' => $customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'INR',
            'lines' => [
                [
                    'description' => 'Product B',
                    'quantity' => 1,
                    'unit_price' => 10000.00,
                    'cgst_rate' => 9.00,
                    'sgst_rate' => 9.00,
                    'hsn_code' => '8471',
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $data = $response->json('data');
        // Subtotal: 10000, CGST: 900, SGST: 900, Tax: 1800, Total: 11800
        $this->assertNotNull($data['tax_amount']);
    }

    public function test_create_invoice_requires_authentication(): void
    {
        $response = $this->postJson("/api/v1{$this->baseUrl}", [
            'customer_id' => $this->customer->id,
        ], ['Accept' => 'application/json']);

        $this->assertUnauthorized($response);
    }

    public function test_create_invoice_validates_required_fields(): void
    {
        $response = $this->apiPost($this->baseUrl, []);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_invoice_validates_customer_exists(): void
    {
        $payload = [
            'customer_id' => 99999,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'description' => 'Test',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_invoice_validates_due_date_after_invoice_date(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->subDays(10)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'description' => 'Test',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_invoice_validates_lines_required(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_invoice_validates_line_quantity_positive(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'description' => 'Test',
                    'quantity' => -5,
                    'unit_price' => 100.00,
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Invoice
    |--------------------------------------------------------------------------
    */

    public function test_can_show_invoice_with_lines(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        InvoiceLine::factory()->count(2)->create([
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$invoice->id}");

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        $this->assertEquals($invoice->id, $data['id']);
        $this->assertArrayHasKey('lines', $data);
    }

    public function test_cannot_show_invoice_from_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherCustomer = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);
        $invoice = Invoice::factory()->create([
            'organization_id' => $otherOrg->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$invoice->id}");

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Invoice
    |--------------------------------------------------------------------------
    */

    public function test_can_update_draft_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
            'notes' => 'Original notes',
        ]);

        $response = $this->apiPut("{$this->baseUrl}/{$invoice->id}", [
            'notes' => 'Updated notes',
            'due_date' => now()->addDays(45)->format('Y-m-d'),
        ]);

        $this->assertSuccessResponse($response);
        $this->assertEquals('Updated notes', $response->json('data.notes'));
    }

    public function test_cannot_update_sent_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
        ]);

        $response = $this->apiPut("{$this->baseUrl}/{$invoice->id}", [
            'notes' => 'Should not update',
        ]);

        // Should be rejected since invoice is not in draft
        $this->assertErrorResponse($response, 422);
    }

    public function test_cannot_update_paid_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_PAID,
        ]);

        $response = $this->apiPut("{$this->baseUrl}/{$invoice->id}", [
            'notes' => 'Should not update',
        ]);

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Invoice
    |--------------------------------------------------------------------------
    */

    public function test_can_delete_draft_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$invoice->id}");

        $this->assertSuccessResponse($response);
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_cannot_delete_sent_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$invoice->id}");

        $this->assertErrorResponse($response, 422);
    }

    public function test_cannot_delete_invoice_from_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherCustomer = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);
        $invoice = Invoice::factory()->create([
            'organization_id' => $otherOrg->id,
            'customer_id' => $otherCustomer->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$invoice->id}");

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Send Invoice
    |--------------------------------------------------------------------------
    */

    /**
     * Create the minimum chart-of-accounts entries needed to send an invoice
     * (receivable account on the customer, plus a sales income account on the line).
     */
    private function setUpInvoiceAccounts(): Account
    {
        $receivable = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_RECEIVABLE,
            'name' => 'Accounts Receivable',
            'code' => '1100',
            'currency_code' => null,
        ]);

        $this->customer->update(['receivable_account_id' => $receivable->id]);

        return Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => Account::TYPE_INCOME,
            'sub_type' => Account::SUBTYPE_SALES,
            'name' => 'Sales Revenue',
            'code' => '4000',
            'currency_code' => null,
        ]);
    }

    public function test_can_send_draft_invoice(): void
    {
        $incomeAccount = $this->setUpInvoiceAccounts();

        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
            'total' => 1000.00,
            'amount_due' => 1000.00,
            'tax_amount' => 0,
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'account_id' => $incomeAccount->id,
            'quantity' => 1,
            'unit_price' => 1000.00,
            'subtotal' => 1000.00,
            'total' => 1000.00,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$invoice->id}/send");

        $this->assertSuccessResponse($response);
        $this->assertEquals(Invoice::STATUS_SENT, $response->json('data.status'));
    }

    public function test_cannot_send_already_sent_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_PAID,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$invoice->id}/send");

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Void Invoice
    |--------------------------------------------------------------------------
    */

    public function test_can_void_draft_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$invoice->id}/void", [
            'reason' => 'Incorrect details',
        ]);

        $this->assertSuccessResponse($response);
        $this->assertEquals(Invoice::STATUS_VOIDED, $response->json('data.status'));
    }

    public function test_can_void_sent_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$invoice->id}/void", [
            'reason' => 'Customer cancelled order',
        ]);

        $this->assertSuccessResponse($response);
        $this->assertEquals(Invoice::STATUS_VOIDED, $response->json('data.status'));
    }

    public function test_cannot_void_paid_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_PAID,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$invoice->id}/void", [
            'reason' => 'Test',
        ]);

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Invoice Status Workflow
    |--------------------------------------------------------------------------
    */

    public function test_invoice_status_workflow_draft_to_sent(): void
    {
        $incomeAccount = $this->setUpInvoiceAccounts();

        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
            'total' => 1000.00,
            'amount_due' => 1000.00,
            'tax_amount' => 0,
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'account_id' => $incomeAccount->id,
            'quantity' => 1,
            'unit_price' => 1000.00,
            'subtotal' => 1000.00,
            'total' => 1000.00,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$invoice->id}/send");

        $this->assertSuccessResponse($response);
        $this->assertEquals(Invoice::STATUS_SENT, $response->json('data.status'));
    }

    public function test_cannot_transition_voided_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_VOIDED,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$invoice->id}/send");

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Invoice Summary
    |--------------------------------------------------------------------------
    */

    public function test_can_get_invoice_summary(): void
    {
        Invoice::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/summary");

        $this->assertSuccessResponse($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Compliance Status
    |--------------------------------------------------------------------------
    */

    public function test_can_check_compliance_status(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
            'compliance_status' => Invoice::COMPLIANCE_PENDING,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$invoice->id}/compliance-status");

        $this->assertSuccessResponse($response);
    }
}
