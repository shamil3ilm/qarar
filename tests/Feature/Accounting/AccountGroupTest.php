<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AccountGroupTest extends TestCase
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
    // Helpers
    // -------------------------------------------------------------------------

    private function makeGroup(array $overrides = []): AccountGroup
    {
        return AccountGroup::create(array_merge([
            'organization_id'  => $this->organization->id,
            'code'             => 'AG-' . fake()->unique()->numerify('###'),
            'name'             => 'Test Account Group',
            'account_category' => 'balance_sheet',
            'is_active'        => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeGroup();
        $this->makeGroup();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/account-groups');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_account_group(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/account-groups', [
                'code'             => 'BS01',
                'name'             => 'Balance Sheet Group',
                'account_category' => 'balance_sheet',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/account-groups', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_account_category_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/account-groups', [
                'code'             => 'XX',
                'name'             => 'Test',
                'account_category' => 'invalid_category',
            ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $this->makeGroup(['code' => 'DUP01']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/account-groups', [
                'code'             => 'DUP01',
                'name'             => 'Duplicate',
                'account_category' => 'balance_sheet',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_group_details(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/account-groups/' . $group->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/account-groups/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_group(): void
    {
        $group = $this->makeGroup(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/account-groups/' . $group->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $group->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_group(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/account-groups/' . $group->uuid);

        $response->assertStatus(200);
        $this->assertNull(AccountGroup::find($group->id));
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/account-groups')->assertStatus(401);
    }
}
