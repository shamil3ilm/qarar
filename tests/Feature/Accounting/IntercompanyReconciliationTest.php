<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\IcReconciliationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class IntercompanyReconciliationTest extends TestCase
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

    private static int $sessionCounter = 0;

    private function makeSession(array $overrides = []): IcReconciliationSession
    {
        self::$sessionCounter++;
        return IcReconciliationSession::create(array_merge([
            'organization_id' => $this->organization->id,
            'session_number'  => 'ICREC-' . str_pad((string) self::$sessionCounter, 6, '0', STR_PAD_LEFT),
            'fiscal_year'     => '2025',
            'period'          => 1,
            'status'          => 'draft',
            'run_by'          => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_sessions_list(): void
    {
        $this->makeSession();
        $this->makeSession(['period' => 2]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/ic-reconciliation/sessions');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/ic-reconciliation/sessions');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Create Session
    // -------------------------------------------------------------------------

    public function test_create_session_opens_new_session(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/ic-reconciliation/sessions', [
                'fiscal_year' => '2025',
                'period'      => 3,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_create_session_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/ic-reconciliation/sessions', []);

        $response->assertStatus(422);
    }

    public function test_create_session_validates_period_range(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/ic-reconciliation/sessions', [
                'fiscal_year' => '2025',
                'period'      => 13,
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_session_details(): void
    {
        $session = $this->makeSession();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/ic-reconciliation/sessions/' . $session->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $session->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/ic-reconciliation/sessions/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Load Items
    // -------------------------------------------------------------------------

    public function test_load_items_validates_required_fields(): void
    {
        $session = $this->makeSession();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/ic-reconciliation/sessions/' . $session->uuid . '/load-items', []);

        $response->assertStatus(422);
    }

    public function test_load_items_validates_item_type_enum(): void
    {
        $session = $this->makeSession();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/ic-reconciliation/sessions/' . $session->uuid . '/load-items', [
                'items' => [[
                    'source_type'      => 'invoice',
                    'source_id'        => 1,
                    'reference_number' => 'INV-001',
                    'amount'           => 1000,
                    'currency'         => 'SAR',
                    'transaction_date' => '2025-01-31',
                    'item_type'        => 'invalid_type',
                ]],
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auto Match
    // -------------------------------------------------------------------------

    public function test_auto_match_returns_success(): void
    {
        $session = $this->makeSession();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/ic-reconciliation/sessions/' . $session->uuid . '/auto-match');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Close
    // -------------------------------------------------------------------------

    public function test_close_session_closes_open_session(): void
    {
        $session = $this->makeSession(['status' => 'draft']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/ic-reconciliation/sessions/' . $session->uuid . '/close');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/ic-reconciliation/sessions')->assertStatus(401);
    }
}
