<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PeriodLockTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.period-lock.manage',
        ]);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_active_overrides(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/period-lock');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Check Period
    // -------------------------------------------------------------------------

    public function test_check_period_validates_date_required(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/period-lock/check');

        $response->assertStatus(422);
    }

    public function test_check_period_returns_lock_status(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/period-lock/check?date=2025-01-15');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['locked']]);
    }

    // -------------------------------------------------------------------------
    // Store (grant override)
    // -------------------------------------------------------------------------

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/period-lock', []);

        $response->assertStatus(422);
    }

    public function test_store_returns_404_for_missing_period(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/period-lock', [
                'period_id' => 99999,
                'user_id'   => $this->user->id,
                'reason'    => 'Testing',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Revoke
    // -------------------------------------------------------------------------

    public function test_revoke_returns_404_for_missing_override(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/period-lock/99999/revoke');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_check_returns_401(): void
    {
        $this->getJson('/api/v1/period-lock/check?date=2025-01-15')->assertStatus(401);
    }
}
