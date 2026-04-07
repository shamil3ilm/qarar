<?php

declare(strict_types=1);

namespace Tests\Feature\CriticalFlows;

use App\Models\Sales\Contact;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Models\Inventory\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class SalesOrderLifecycleTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.orders.view',
            'sales.orders.create',
            'sales.orders.edit',
            'sales.orders.confirm',
            'sales.orders.cancel',
            'sales.orders.convert',
            'sales.orders.deliver',
            'sales.invoices.create',
            'sales.invoices.view',
            'sales.contacts.view',
            'sales.contacts.create',
        ]);
        $this->setUpOpenFiscalPeriod();

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_CUSTOMER,
            'currency_code'   => 'SAR',
        ]);
    }

    public function test_can_create_sales_order_in_draft_state(): void
    {
        $response = $this->apiPost('/sales/sales-orders', [
            'customer_id'    => $this->customer->id,
            'customer_name'  => $this->customer->name,
            'order_date'     => now()->format('Y-m-d'),
            'currency_code'  => 'SAR',
            'lines'          => [
                [
                    'description' => 'Product A',
                    'quantity'    => 5,
                    'unit_price'  => 200.00,
                    'tax_rate'    => 15,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', SalesOrder::STATUS_DRAFT);
    }

    public function test_can_confirm_draft_sales_order(): void
    {
        $order = SalesOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'currency_code'   => 'SAR',
            'status'          => SalesOrder::STATUS_DRAFT,
        ]);

        // Confirm requires at least one line item
        SalesOrderLine::factory()->create([
            'sales_order_id' => $order->id,
            'quantity'       => 5,
            'unit_price'     => 200.00,
        ]);

        $response = $this->apiPost("/sales/sales-orders/{$order->id}/confirm");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', \App\Models\Sales\SalesOrder::STATUS_CONFIRMED);
    }

    public function test_can_convert_confirmed_order_to_invoice(): void
    {
        // canBeInvoiced() requires PARTIALLY_DELIVERED or DELIVERED status
        $order = SalesOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'currency_code'   => 'SAR',
            'status'          => SalesOrder::STATUS_DELIVERED,
            'subtotal'        => 1000.00,
            'tax_amount'      => 150.00,
            'total'           => 1150.00,
        ]);

        // sales_order_lines table has no organization_id column — do not pass it
        SalesOrderLine::factory()->create([
            'sales_order_id'    => $order->id,
            'quantity'          => 5,
            'quantity_delivered' => 5,
            'unit_price'        => 200.00,
        ]);

        $response = $this->apiPost("/sales/sales-orders/{$order->id}/convert-to-invoice", [
            'invoice_date' => now()->format('Y-m-d'),
            'due_date'     => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
    }

    public function test_can_cancel_sales_order(): void
    {
        $order = SalesOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'currency_code'   => 'SAR',
            'status'          => SalesOrder::STATUS_DRAFT,
        ]);

        $response = $this->apiPost("/sales/sales-orders/{$order->id}/cancel", [
            'reason' => 'Customer requested cancellation',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', SalesOrder::STATUS_CANCELLED);
    }

    public function test_cannot_cancel_invoiced_sales_order(): void
    {
        $order = SalesOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'currency_code'   => 'SAR',
            'status'          => SalesOrder::STATUS_INVOICED,
        ]);

        $response = $this->apiPost("/sales/sales-orders/{$order->id}/cancel", [
            'reason' => 'Trying to cancel an invoiced order',
        ]);

        $response->assertStatus(422);
    }
}
