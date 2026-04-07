<?php

declare(strict_types=1);

namespace Tests\Feature\CriticalFlows;

use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderLine;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PurchaseFlowTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'purchase.orders.view',
            'purchase.orders.create',
            'purchase.orders.edit',
            'purchase.orders.confirm',
            'purchase.orders.receive',
            'purchase.orders.send',
            'purchase.bills.view',
            'purchase.bills.create',
        ]);
        $this->setUpOpenFiscalPeriod();
    }

    public function test_can_create_purchase_order(): void
    {
        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_SUPPLIER,
            'currency_code'   => 'SAR',
        ]);

        $response = $this->apiPost('/purchase/purchase-orders', [
            'supplier_id'            => $supplier->id,
            'supplier_name'          => $supplier->name,
            'order_date'             => now()->format('Y-m-d'),
            'expected_delivery_date' => now()->addDays(14)->format('Y-m-d'),
            'currency_code'          => 'SAR',
            'lines'                  => [
                [
                    'description' => 'Office Supplies',
                    'quantity'    => 10,
                    'unit_price'  => 50.00,
                    'tax_rate'    => 15,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', PurchaseOrder::STATUS_DRAFT);
    }

    public function test_can_confirm_purchase_order(): void
    {
        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_SUPPLIER,
        ]);

        $po = PurchaseOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'supplier_id'     => $supplier->id,
            'currency_code'   => 'SAR',
            'status'          => PurchaseOrder::STATUS_DRAFT,
        ]);

        $response = $this->apiPost("/purchase/purchase-orders/{$po->id}/confirm");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', PurchaseOrder::STATUS_CONFIRMED);
    }

    public function test_can_receive_confirmed_purchase_order(): void
    {
        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_SUPPLIER,
        ]);

        $po = PurchaseOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'supplier_id'     => $supplier->id,
            'currency_code'   => 'SAR',
            'status'          => PurchaseOrder::STATUS_CONFIRMED,
        ]);

        $line = PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $po->id,
            'quantity'          => 10,
            'unit_price'        => 50.00,
        ]);

        $response = $this->apiPost("/purchase/purchase-orders/{$po->id}/receive", [
            'line_quantities' => [$line->id => 10],
        ]);

        // Receiving a confirmed PO should succeed (200) or transition to received/partially_received
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_can_create_bill_from_purchase_order(): void
    {
        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_SUPPLIER,
        ]);

        $po = PurchaseOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'supplier_id'     => $supplier->id,
            'currency_code'   => 'SAR',
            'status'          => PurchaseOrder::STATUS_RECEIVED,
        ]);

        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $po->id,
            'quantity'          => 5,
            'unit_price'        => 100.00,
            'quantity_received' => 5,  // needed for getRemainingToBill() to return > 0
        ]);

        $response = $this->apiPost('/purchase/bills/from-purchase-order', [
            'purchase_order_id' => $po->id,
            'bill_date'         => now()->format('Y-m-d'),
            'due_date'          => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
    }

    public function test_cannot_confirm_cancelled_purchase_order(): void
    {
        $supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_SUPPLIER,
        ]);

        $po = PurchaseOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'supplier_id'     => $supplier->id,
            'status'          => PurchaseOrder::STATUS_CANCELLED,
        ]);

        $response = $this->apiPost("/purchase/purchase-orders/{$po->id}/confirm");

        // A cancelled PO may be filtered by global scope (404) or explicitly rejected (422)
        $this->assertTrue(
            in_array($response->status(), [404, 422]),
            "Expected 404 or 422, got {$response->status()}"
        );
    }
}
