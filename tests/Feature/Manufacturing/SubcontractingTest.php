<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Manufacturing\SubcontractOrder;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class SubcontractingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $vendor;
    private Product $product;
    private UnitOfMeasure $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.subcontracting.view',
            'manufacturing.subcontracting.create',
            'manufacturing.subcontracting.edit',
            'manufacturing.subcontracting.close',
            'manufacturing.subcontracting.cancel',
        ]);

        $this->vendor  = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
        $this->unit    = UnitOfMeasure::factory()->create(['organization_id' => $this->organization->id]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_orders(): void
    {
        SubcontractOrder::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'contact_id'      => $this->vendor->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/subcontract-orders', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_order(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/subcontract-orders',
            [
                'contact_id' => $this->vendor->id,
                'lines'      => [[
                    'product_id'        => $this->product->id,
                    'ordered_quantity'  => 10,
                    'unit_id'           => $this->unit->id,
                ]],
            ],
            $this->authHeaders()
        );

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertDatabaseHas('subcontract_orders', [
            'organization_id' => $this->organization->id,
            'contact_id'      => $this->vendor->id,
        ]);
    }

    public function test_store_requires_contact_id(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/subcontract-orders',
            [
                'lines' => [[
                    'product_id'       => $this->product->id,
                    'ordered_quantity' => 10,
                    'unit_id'          => $this->unit->id,
                ]],
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_lines(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/subcontract-orders',
            ['contact_id' => $this->vendor->id],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_order(): void
    {
        $order = SubcontractOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_id'      => $this->vendor->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/subcontract-orders/{$order->uuid}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/subcontract-orders')->assertUnauthorized();
    }
}
