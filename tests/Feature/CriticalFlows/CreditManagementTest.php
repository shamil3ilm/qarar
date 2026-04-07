<?php

declare(strict_types=1);

namespace Tests\Feature\CriticalFlows;

use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CreditManagementTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'accounting.credit.view',
            'accounting.credit.manage',
            'accounting.credit.hold',
            'sales.invoices.view',
            'sales.invoices.create',
            'sales.orders.view',
            'sales.contacts.view',
            'sales.contacts.create',
            'sales.contacts.edit',
        ]);
        $this->setUpOpenFiscalPeriod();

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_CUSTOMER,
            'currency_code'   => 'SAR',
        ]);
    }

    public function test_can_set_credit_limit_for_customer(): void
    {
        $response = $this->apiPost('/credit-management/limits', [
            'contact_id'    => $this->customer->id,
            'credit_limit'  => 100000.00,
            'currency_code' => 'SAR',
            'valid_from'    => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
    }

    public function test_can_view_credit_exposure_for_customer(): void
    {
        $response = $this->apiGet("/credit-management/exposure/contacts/{$this->customer->id}");

        // Should return 200 with exposure data (even if zero exposure)
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_can_list_credit_limits(): void
    {
        $response = $this->apiGet('/credit-management/limits');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_can_place_credit_hold_on_customer(): void
    {
        // Controller expects 'hold_reason', not 'reason'
        $response = $this->apiPost("/credit-management/holds/contacts/{$this->customer->id}", [
            'hold_reason' => 'Overdue invoices exceed limit',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
    }

    public function test_sales_order_credit_check_endpoint_is_accessible(): void
    {
        $order = \App\Models\Sales\SalesOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'currency_code'   => 'SAR',
            'status'          => \App\Models\Sales\SalesOrder::STATUS_DRAFT,
            'total'           => 5000.00,
        ]);

        $this->setUpAuthenticatedUser([
            'sales.orders.view',
            'accounting.credit.view',
        ]);

        $response = $this->apiGet("/sales/sales-orders/{$order->id}/credit-check");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.customer_id', $this->customer->id);
        $this->assertEquals(5000.0, $response->json('data.order_amount'));
    }
}
