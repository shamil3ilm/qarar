<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class MaterialLedgerTest extends TestCase
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
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/material-ledger/records');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_index_filters_by_period(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/material-ledger/records?period=3&fiscal_year=2025');

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Show (product records)
    // -------------------------------------------------------------------------

    public function test_show_returns_records_for_product(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/material-ledger/records/999');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Period Close
    // -------------------------------------------------------------------------

    public function test_period_close_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/material-ledger/period-close', []);

        $response->assertStatus(422);
    }

    public function test_period_close_validates_period_range(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/material-ledger/period-close', [
                'period'      => 13,
                'fiscal_year' => 2025,
            ]);

        $response->assertStatus(422);
    }

    public function test_period_close_runs_with_no_open_records(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/material-ledger/period-close', [
                'period'      => 3,
                'fiscal_year' => 2025,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Period Report
    // -------------------------------------------------------------------------

    public function test_period_report_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/material-ledger/period-report');

        $response->assertStatus(422);
    }

    public function test_period_report_returns_summary(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/material-ledger/period-report?period=3&fiscal_year=2025');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Closing Entries
    // -------------------------------------------------------------------------

    public function test_closing_entries_returns_paginated_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/material-ledger/closing-entries');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/material-ledger/records')->assertStatus(401);
    }
}
