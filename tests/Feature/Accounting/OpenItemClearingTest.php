<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class OpenItemClearingTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
    }

    // -------------------------------------------------------------------------
    // AR Open Items
    // -------------------------------------------------------------------------

    public function test_ar_open_items_validates_customer_id_required(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/open-items/ar');

        $response->assertStatus(422);
    }

    public function test_ar_open_items_returns_empty_for_unknown_customer(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/open-items/ar?customer_id=999');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Clear AR
    // -------------------------------------------------------------------------

    public function test_clear_ar_validates_payment_id_required(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/open-items/ar/clear', []);

        $response->assertStatus(422);
    }

    public function test_clear_ar_returns_404_for_missing_payment(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/open-items/ar/clear', ['payment_id' => 99999]);

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // AP Open Items
    // -------------------------------------------------------------------------

    public function test_ap_open_items_validates_supplier_id_required(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/open-items/ap');

        $response->assertStatus(422);
    }

    public function test_ap_open_items_returns_empty_for_unknown_supplier(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/open-items/ap?supplier_id=999');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Clear AP
    // -------------------------------------------------------------------------

    public function test_clear_ap_validates_payment_id_required(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/open-items/ap/clear', []);

        $response->assertStatus(422);
    }

    public function test_clear_ap_returns_404_for_missing_payment(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/open-items/ap/clear', ['payment_id' => 99999]);

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/open-items/ar?customer_id=1')->assertStatus(401);
    }
}
