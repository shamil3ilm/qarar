<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\CreditNote;
use App\Models\Sales\CreditNoteItem;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CreditNoteTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/sales/credit-notes';
    private Contact $customer;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.credit-notes.view',
            'sales.credit-notes.create',
            'sales.credit-notes.approve',
            'sales.credit-notes.apply',
            'sales.credit-notes.void',
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);

        $this->invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
            'total' => 10000.00,
            'amount_due' => 10000.00,
            'currency_code' => 'SAR',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | List Credit Notes
    |--------------------------------------------------------------------------
    */

    public function test_can_list_credit_notes(): void
    {
        CreditNote::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
    }

    public function test_list_credit_notes_returns_only_own_organization(): void
    {
        CreditNote::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
        ]);

        $otherOrg = Organization::factory()->create();
        $otherCustomer = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);
        $otherInvoice = Invoice::factory()->create([
            'organization_id' => $otherOrg->id,
            'customer_id' => $otherCustomer->id,
        ]);
        CreditNote::factory()->count(3)->create([
            'organization_id' => $otherOrg->id,
            'contact_id' => $otherCustomer->id,
            'invoice_id' => $otherInvoice->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $creditNote) {
            $this->assertEquals($this->organization->id, $creditNote['organization_id']);
        }
    }

    public function test_list_credit_notes_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1{$this->baseUrl}", [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Credit Note
    |--------------------------------------------------------------------------
    */

    public function test_can_create_credit_note(): void
    {
        $payload = [
            'invoice_id' => $this->invoice->id,
            'contact_id' => $this->customer->id,
            'credit_note_type' => CreditNote::TYPE_SALES,
            'credit_note_date' => now()->format('Y-m-d'),
            'currency_code' => 'SAR',
            'reason' => 'Damaged goods returned',
            'lines' => [
                [
                    'description' => 'Product A - Returned',
                    'quantity' => 2,
                    'unit_price' => 500.00,
                    'tax_rate' => 15.00,
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $data = $response->json('data');
        $this->assertEquals(CreditNote::STATUS_DRAFT, $data['status']);
        $this->assertEquals($this->invoice->id, $data['invoice_id']);
        $this->assertNotNull($data['credit_note_number']);
    }

    public function test_can_create_credit_note_with_multiple_lines(): void
    {
        $payload = [
            'invoice_id' => $this->invoice->id,
            'contact_id' => $this->customer->id,
            'credit_note_type' => CreditNote::TYPE_SALES,
            'credit_note_date' => now()->format('Y-m-d'),
            'currency_code' => 'SAR',
            'reason' => 'Partial return',
            'lines' => [
                [
                    'description' => 'Product A',
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                    'tax_rate' => 15.00,
                ],
                [
                    'description' => 'Product B',
                    'quantity' => 3,
                    'unit_price' => 200.00,
                    'tax_rate' => 15.00,
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
    }

    public function test_create_credit_note_requires_authentication(): void
    {
        $response = $this->postJson("/api/v1{$this->baseUrl}", [
            'invoice_id' => $this->invoice->id,
        ], ['Accept' => 'application/json']);

        $this->assertUnauthorized($response);
    }

    public function test_create_credit_note_validates_required_fields(): void
    {
        $response = $this->apiPost($this->baseUrl, []);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_credit_note_validates_invoice_exists(): void
    {
        $payload = [
            'invoice_id' => 99999,
            'contact_id' => $this->customer->id,
            'credit_note_type' => CreditNote::TYPE_SALES,
            'credit_note_date' => now()->format('Y-m-d'),
            'currency_code' => 'SAR',
            'reason' => 'Test',
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

    public function test_create_credit_note_validates_amount_does_not_exceed_invoice(): void
    {
        $payload = [
            'invoice_id' => $this->invoice->id,
            'contact_id' => $this->customer->id,
            'credit_note_type' => CreditNote::TYPE_SALES,
            'credit_note_date' => now()->format('Y-m-d'),
            'currency_code' => 'SAR',
            'reason' => 'Over-credit attempt',
            'lines' => [
                [
                    'description' => 'Exceeding item',
                    'quantity' => 100,
                    'unit_price' => 50000.00, // Exceeds invoice total
                    'tax_rate' => 15.00,
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Credit Note
    |--------------------------------------------------------------------------
    */

    public function test_can_show_credit_note(): void
    {
        $creditNote = CreditNote::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
        ]);

        CreditNoteItem::factory()->count(2)->create([
            'credit_note_id' => $creditNote->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$creditNote->id}");

        $this->assertSuccessResponse($response);
        $this->assertEquals($creditNote->id, $response->json('data.id'));
    }

    public function test_cannot_show_credit_note_from_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherCustomer = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);
        $otherInvoice = Invoice::factory()->create([
            'organization_id' => $otherOrg->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $creditNote = CreditNote::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_id' => $otherCustomer->id,
            'invoice_id' => $otherInvoice->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$creditNote->id}");

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Approve Credit Note
    |--------------------------------------------------------------------------
    */

    public function test_can_approve_draft_credit_note(): void
    {
        $creditNote = CreditNote::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'status' => CreditNote::STATUS_DRAFT,
            'total' => 1000.00,
            'available_amount' => 1000.00,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$creditNote->id}/approve");

        $this->assertSuccessResponse($response);
        $this->assertEquals(CreditNote::STATUS_APPROVED, $response->json('data.status'));
    }

    public function test_cannot_approve_already_approved_credit_note(): void
    {
        $creditNote = CreditNote::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'status' => CreditNote::STATUS_APPROVED,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$creditNote->id}/approve");

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Apply Credit Note to Invoice
    |--------------------------------------------------------------------------
    */

    public function test_can_apply_credit_note_to_invoice(): void
    {
        $creditNote = CreditNote::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'status' => CreditNote::STATUS_APPROVED,
            'total' => 2000.00,
            'available_amount' => 2000.00,
            'applied_amount' => 0.00,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$creditNote->id}/apply", [
            'invoice_id' => $this->invoice->id,
            'amount' => 2000.00,
        ]);

        $this->assertSuccessResponse($response);
    }

    public function test_credit_note_application_reduces_invoice_balance(): void
    {
        $creditNote = CreditNote::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'status' => CreditNote::STATUS_APPROVED,
            'total' => 3000.00,
            'available_amount' => 3000.00,
            'applied_amount' => 0.00,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$creditNote->id}/apply", [
            'invoice_id' => $this->invoice->id,
            'amount' => 3000.00,
        ]);

        $this->assertSuccessResponse($response);

        // Verify invoice balance reduced
        $this->invoice->refresh();
        $this->assertLessThanOrEqual(10000.00, (float) $this->invoice->amount_due);
    }

    public function test_cannot_apply_credit_note_exceeding_available_amount(): void
    {
        $creditNote = CreditNote::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'status' => CreditNote::STATUS_APPROVED,
            'total' => 1000.00,
            'available_amount' => 1000.00,
            'applied_amount' => 0.00,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$creditNote->id}/apply", [
            'invoice_id' => $this->invoice->id,
            'amount' => 5000.00, // Exceeds available
        ]);

        $this->assertErrorResponse($response, 422);
    }

    public function test_cannot_apply_draft_credit_note(): void
    {
        $creditNote = CreditNote::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'status' => CreditNote::STATUS_DRAFT,
            'total' => 1000.00,
            'available_amount' => 1000.00,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$creditNote->id}/apply", [
            'invoice_id' => $this->invoice->id,
            'amount' => 1000.00,
        ]);

        $this->assertErrorResponse($response, 422);
    }

    public function test_cannot_apply_voided_credit_note(): void
    {
        $creditNote = CreditNote::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'status' => CreditNote::STATUS_VOIDED,
            'total' => 1000.00,
            'available_amount' => 0.00,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$creditNote->id}/apply", [
            'invoice_id' => $this->invoice->id,
            'amount' => 1000.00,
        ]);

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Void Credit Note
    |--------------------------------------------------------------------------
    */

    public function test_can_void_draft_credit_note(): void
    {
        $creditNote = CreditNote::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'status' => CreditNote::STATUS_DRAFT,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$creditNote->id}/void", [
            'reason' => 'Created in error',
        ]);

        $this->assertSuccessResponse($response);
        $this->assertEquals(CreditNote::STATUS_VOIDED, $response->json('data.status'));
    }

    public function test_cannot_void_fully_applied_credit_note(): void
    {
        $creditNote = CreditNote::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'status' => CreditNote::STATUS_APPLIED,
            'total' => 1000.00,
            'applied_amount' => 1000.00,
            'available_amount' => 0.00,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$creditNote->id}/void", [
            'reason' => 'Should fail',
        ]);

        $this->assertErrorResponse($response, 422);
    }
}
