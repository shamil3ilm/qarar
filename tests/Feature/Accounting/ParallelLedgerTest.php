<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\SpecialLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ParallelLedgerTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.ledgers.view',
            'accounting.ledgers.manage',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeLedger(array $overrides = []): SpecialLedger
    {
        return SpecialLedger::create(array_merge([
            'organization_id'      => $this->organization->id,
            'code'                 => 'PL-' . fake()->unique()->numerify('##'),
            'name'                 => 'IFRS Ledger',
            'accounting_principle' => 'IFRS',
            'is_leading'           => false,
            'is_active'            => true,
            'currency_code'        => 'SAR',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_ledger_list(): void
    {
        $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parallel-ledgers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_parallel_ledger(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parallel-ledgers', [
                'code'                 => 'IFRS',
                'name'                 => 'IFRS Ledger',
                'accounting_principle' => 'IFRS',
                'currency_code'        => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parallel-ledgers', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_accounting_principle_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parallel-ledgers', [
                'code'                 => 'XX',
                'name'                 => 'Test',
                'accounting_principle' => 'INVALID',
                'currency_code'        => 'SAR',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Comparison
    // -------------------------------------------------------------------------

    public function test_comparison_validates_fiscal_year_required(): void
    {
        $ledger = $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parallel-ledgers/' . $ledger->id . '/comparison');

        $response->assertStatus(422);
    }

    public function test_comparison_returns_data_for_valid_ledger(): void
    {
        $ledger = $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parallel-ledgers/' . $ledger->id . '/comparison?fiscal_year=2025');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Post Entry
    // -------------------------------------------------------------------------

    public function test_post_entry_returns_404_for_missing_ledger(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parallel-ledgers/99999/post/1');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/parallel-ledgers')->assertStatus(401);
    }
}
