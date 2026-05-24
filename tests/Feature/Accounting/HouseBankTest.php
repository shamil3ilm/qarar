<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\HouseBank;
use App\Models\Accounting\PaymentAdvice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class HouseBankTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.housebank.view',
            'accounting.housebank.manage',
            'accounting.housebank.create',
            'accounting.housebank.send',
            'accounting.housebank.acknowledge',
            'accounting.housebank.cancel',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeHouseBank(array $overrides = []): HouseBank
    {
        return HouseBank::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'HB' . fake()->unique()->numerify('##'),
            'name'            => 'Test House Bank',
            'is_active'       => true,
        ], $overrides));
    }

    private function makeAdvice(HouseBank $bank, array $overrides = []): PaymentAdvice
    {
        return PaymentAdvice::create(array_merge([
            'organization_id' => $this->organization->id,
            'house_bank_id'   => $bank->id,
            'direction'       => 'outgoing',
            'currency_code'   => 'SAR',
            'amount'          => 5000.00,
            'payment_date'    => '2025-01-31',
            'status'          => 'draft',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // House Banks — Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeHouseBank();
        $this->makeHouseBank();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/house-banks');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/house-banks');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // House Banks — Store
    // -------------------------------------------------------------------------

    public function test_store_creates_house_bank(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/house-banks', [
                'code' => 'RIYAD',
                'name' => 'Riyad Bank',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'RIYAD');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/house-banks', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // House Banks — Show
    // -------------------------------------------------------------------------

    public function test_show_returns_house_bank_details(): void
    {
        $bank = $this->makeHouseBank();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/house-banks/' . $bank->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $bank->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/house-banks/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // House Banks — Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_house_bank(): void
    {
        $bank = $this->makeHouseBank(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/house-banks/' . $bank->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $bank->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // House Banks — Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_house_bank(): void
    {
        $bank = $this->makeHouseBank();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/house-banks/' . $bank->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('house_banks', ['id' => $bank->id]);
    }

    // -------------------------------------------------------------------------
    // House Bank Accounts
    // -------------------------------------------------------------------------

    public function test_add_account_creates_account(): void
    {
        $bank = $this->makeHouseBank();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/house-banks/' . $bank->uuid . '/accounts', [
                'account_id_code' => 'SAR001',
                'currency_code'   => 'SAR',
                'account_purpose' => 'both',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_add_account_validates_required_fields(): void
    {
        $bank = $this->makeHouseBank();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/house-banks/' . $bank->uuid . '/accounts', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Payment Advices — Index / Show
    // -------------------------------------------------------------------------

    public function test_index_advices_returns_list(): void
    {
        $bank = $this->makeHouseBank();
        $this->makeAdvice($bank);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-advices');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_store_advice_creates_advice(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-advices', [
                'direction'    => 'outgoing',
                'amount'       => 1000,
                'payment_date' => '2025-01-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_advice_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-advices', []);

        $response->assertStatus(422);
    }

    public function test_show_advice_returns_details(): void
    {
        $bank   = $this->makeHouseBank();
        $advice = $this->makeAdvice($bank);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-advices/' . $advice->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $advice->id);
    }

    // -------------------------------------------------------------------------
    // Advice state transitions
    // -------------------------------------------------------------------------

    public function test_send_advice_marks_as_sent(): void
    {
        $bank   = $this->makeHouseBank();
        $advice = $this->makeAdvice($bank, ['status' => 'draft']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-advices/' . $advice->uuid . '/send');

        $response->assertStatus(200);
        $this->assertEquals('sent', $advice->fresh()->status);
    }

    public function test_acknowledge_advice_marks_as_acknowledged(): void
    {
        $bank   = $this->makeHouseBank();
        $advice = $this->makeAdvice($bank, ['status' => 'sent']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-advices/' . $advice->uuid . '/acknowledge');

        $response->assertStatus(200);
        $this->assertEquals('acknowledged', $advice->fresh()->status);
    }

    public function test_cancel_advice_marks_as_cancelled(): void
    {
        $bank   = $this->makeHouseBank();
        $advice = $this->makeAdvice($bank, ['status' => 'draft']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-advices/' . $advice->uuid . '/cancel');

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $advice->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/house-banks')->assertStatus(401);
    }
}
