<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\QualityCostEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class QualityCostTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_entries(): void
    {
        QualityCostEntry::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/quality-costs', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_entry(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/quality-costs',
            [
                'cost_category' => 'prevention',
                'period'        => 5,
                'fiscal_year'   => 2026,
                'amount'        => 1500.00,
                'description'   => 'Training costs',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('quality_cost_entries', [
            'organization_id' => $this->organization->id,
            'cost_category'   => 'prevention',
            'fiscal_year'     => 2026,
        ]);
    }

    public function test_store_requires_cost_category(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/quality-costs',
            ['period' => 5, 'fiscal_year' => 2026, 'amount' => 100],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_amount(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/quality-costs',
            ['cost_category' => 'prevention', 'period' => 5, 'fiscal_year' => 2026],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_validates_category_values(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/quality-costs',
            ['cost_category' => 'invalid', 'period' => 5, 'fiscal_year' => 2026, 'amount' => 100],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_entry(): void
    {
        $entry = QualityCostEntry::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/quality-costs/{$entry->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_changes_amount(): void
    {
        $entry = QualityCostEntry::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->putJson(
            "/api/v1/manufacturing/quality-costs/{$entry->id}",
            ['amount' => 9999.99],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('quality_cost_entries', [
            'id'     => $entry->id,
            'amount' => '9999.9900',
        ]);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_entry(): void
    {
        $entry = QualityCostEntry::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/manufacturing/quality-costs/{$entry->id}",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── summary ─────────────────────────────────────────────────────────────

    public function test_summary_requires_period_and_year(): void
    {
        $response = $this->getJson(
            '/api/v1/manufacturing/quality-costs/summary',
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_summary_returns_data(): void
    {
        $response = $this->getJson(
            '/api/v1/manufacturing/quality-costs/summary?period=5&fiscal_year=2026',
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/quality-costs')->assertUnauthorized();
    }
}
