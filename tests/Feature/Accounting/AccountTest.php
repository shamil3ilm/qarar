<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AccountTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.accounts.view',
            'accounting.accounts.create',
            'accounting.accounts.update',
            'accounting.accounts.delete',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAccount(array $overrides = []): Account
    {
        return Account::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'AC-' . fake()->unique()->numerify('####'),
            'name'            => 'Test Account',
            'account_type'    => 'asset',
            'sub_type'        => 'bank',
            'is_active'       => true,
            'is_header'       => false,
            'level'           => 1,
            'path'            => 'AC-' . fake()->unique()->numerify('####'),
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_tree(): void
    {
        $this->makeAccount();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/accounts');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Flat
    // -------------------------------------------------------------------------

    public function test_flat_returns_list(): void
    {
        $this->makeAccount();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/accounts/flat');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/accounts', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_account(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/accounts', [
                'code'         => 'CASH-001',
                'name'         => 'Cash on Hand',
                'account_type' => 'asset',
                'sub_type'     => 'cash',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $account = $this->makeAccount(['code' => 'DUP-001']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/accounts', [
                'code'         => 'DUP-001',
                'name'         => 'Duplicate',
                'account_type' => 'asset',
                'sub_type'     => 'cash',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_details(): void
    {
        $account = $this->makeAccount();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/accounts/' . $account->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/accounts/999999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_account(): void
    {
        $account = $this->makeAccount(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/accounts/' . $account->id, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $account->fresh()->name);
    }

    public function test_update_rejects_system_account(): void
    {
        $account = $this->makeAccount(['is_system' => true]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/accounts/' . $account->id, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_account(): void
    {
        $account = $this->makeAccount();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/accounts/' . $account->id);

        $response->assertStatus(200);
        $this->assertNull(Account::find($account->id));
    }

    public function test_destroy_rejects_system_account(): void
    {
        $account = $this->makeAccount(['is_system' => true]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/accounts/' . $account->id);

        $response->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Ledger
    // -------------------------------------------------------------------------

    public function test_ledger_returns_data(): void
    {
        $account = $this->makeAccount();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/accounts/' . $account->id . '/ledger');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/accounts')->assertStatus(401);
    }
}
