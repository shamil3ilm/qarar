<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Inventory\Product;
use App\Models\Purchase\Bill;
use App\Models\Purchase\BillLine;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class BillTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/purchase/bills';

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

    public function test_unauthenticated_user_cannot_access_bills(): void
    {
        $response = $this->getJson("/api/v1{$this->baseUrl}");

        $this->assertUnauthorized($response);
    }

    public function test_unauthenticated_user_cannot_create_bill(): void
    {
        $response = $this->postJson("/api/v1{$this->baseUrl}", []);

        $this->assertUnauthorized($response);
    }

    /*
    |--------------------------------------------------------------------------
    | List Bills
    |--------------------------------------------------------------------------
    */

    public function test_can_list_bills(): void
    {
        $this->setUpAuthenticatedUser(['purchase.bills.view']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        Bill::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
    }

    public function test_list_bills_respects_multi_tenant_isolation(): void
    {
        $this->setUpAuthenticatedUser(['purchase.bills.view']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        Bill::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->user->id,
        ]);

        // Create bill in another organization
        $otherOrg = \App\Models\Core\Organization::factory()->create();
        $otherBranch = \App\Models\Core\Branch::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherSupplier = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);
        Bill::factory()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'supplier_id' => $otherSupplier->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');
        foreach ($data as $bill) {
            $this->assertEquals($this->organization->id, $bill['organization_id']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create Bill
    |--------------------------------------------------------------------------
    */

    public function test_can_create_bill(): void
    {
        $this->setUpAuthenticatedUser(['purchase.bills.create']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'bill_number' => 'BILL-2026-001',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'product_id' => $product->id,
                    'description' => 'Office supplies',
                    'quantity' => 5,
                    'unit_price' => 100.00,
                    'tax_rate' => 15,
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $response->assertJsonPath('data.status', Bill::STATUS_DRAFT);
        $response->assertJsonPath('data.supplier_id', $supplier->id);
        $response->assertJsonPath('data.bill_number', 'BILL-2026-001');
    }

    public function test_create_bill_validates_required_fields(): void
    {
        $this->setUpAuthenticatedUser(['purchase.bills.create']);

        $response = $this->apiPost($this->baseUrl, []);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['supplier_id', 'bill_date', 'due_date', 'lines']);
    }

    public function test_create_bill_validates_due_date_not_before_bill_date(): void
    {
        $this->setUpAuthenticatedUser(['purchase.bills.create']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $payload = [
            'supplier_id' => $supplier->id,
            'bill_number' => 'BILL-2026-002',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
            'lines' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 50.00,
                ],
            ],
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Bill
    |--------------------------------------------------------------------------
    */

    public function test_can_show_bill(): void
    {
        $this->setUpAuthenticatedUser(['purchase.bills.view']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$bill->id}");

        $this->assertSuccessResponse($response);
        $response->assertJsonPath('data.id', $bill->id);
    }

    public function test_cannot_show_bill_from_another_organization(): void
    {
        $this->setUpAuthenticatedUser(['purchase.bills.view']);

        $otherOrg = \App\Models\Core\Organization::factory()->create();
        $otherBranch = \App\Models\Core\Branch::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherSupplier = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $bill = Bill::factory()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'supplier_id' => $otherSupplier->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$bill->id}");

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Bill
    |--------------------------------------------------------------------------
    */

    public function test_can_update_draft_bill(): void
    {
        $this->setUpAuthenticatedUser(['purchase.bills.edit']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'status' => Bill::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'due_date' => now()->addDays(45)->toDateString(),
            'notes' => 'Extended payment terms',
        ];

        $response = $this->apiPut("{$this->baseUrl}/{$bill->id}", $payload);

        $this->assertSuccessResponse($response);
    }

    public function test_cannot_update_approved_bill(): void
    {
        $this->setUpAuthenticatedUser(['purchase.bills.edit']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'status' => Bill::STATUS_APPROVED,
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'notes' => 'Trying to update approved bill',
        ];

        $response = $this->apiPut("{$this->baseUrl}/{$bill->id}", $payload);

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Approve Bill
    |--------------------------------------------------------------------------
    */

    public function test_can_approve_draft_bill(): void
    {
        $this->setUpAuthenticatedUser(['purchase.bills.approve']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'status' => Bill::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        BillLine::factory()->create([
            'bill_id' => $bill->id,
            'product_id' => $product->id,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$bill->id}/approve");

        $this->assertSuccessResponse($response);
        $response->assertJsonPath('data.status', Bill::STATUS_APPROVED);
    }

    public function test_cannot_approve_paid_bill(): void
    {
        $this->setUpAuthenticatedUser(['purchase.bills.approve']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'status' => Bill::STATUS_PAID,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$bill->id}/approve");

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Bill
    |--------------------------------------------------------------------------
    */

    public function test_can_delete_draft_bill(): void
    {
        $this->setUpAuthenticatedUser(['purchase.bills.delete']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'status' => Bill::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$bill->id}");

        $this->assertSuccessResponse($response);
        $this->assertSoftDeleted('bills', ['id' => $bill->id]);
    }

    public function test_cannot_delete_approved_bill(): void
    {
        $this->setUpAuthenticatedUser(['purchase.bills.delete']);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'status' => Bill::STATUS_APPROVED,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$bill->id}");

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Bill Status Workflow
    |--------------------------------------------------------------------------
    */

    public function test_bill_follows_status_workflow(): void
    {
        $this->setUpAuthenticatedUser([
            'purchase.bills.create',
            'purchase.bills.approve',
        ]);

        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Step 1: Create bill (draft)
        $payload = [
            'supplier_id' => $supplier->id,
            'bill_number' => 'BILL-WF-001',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'lines' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_price' => 50.00,
                ],
            ],
        ];

        $createResponse = $this->apiPost($this->baseUrl, $payload);
        $this->assertCreatedResponse($createResponse);
        $billId = $createResponse->json('data.id');
        $createResponse->assertJsonPath('data.status', Bill::STATUS_DRAFT);

        // Step 2: Approve the bill
        $approveResponse = $this->apiPost("{$this->baseUrl}/{$billId}/approve");
        $this->assertSuccessResponse($approveResponse);
        $approveResponse->assertJsonPath('data.status', Bill::STATUS_APPROVED);
    }

    /*
    |--------------------------------------------------------------------------
    | Permission Checks
    |--------------------------------------------------------------------------
    */

    public function test_user_without_permission_cannot_create_bill(): void
    {
        $this->setUpAuthenticatedUser([]);

        $response = $this->apiPost($this->baseUrl, [
            'supplier_id' => 1,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'lines' => [],
        ]);

        $this->assertForbidden($response);
    }
}
