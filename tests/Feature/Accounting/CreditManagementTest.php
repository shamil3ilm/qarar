<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CreditHold;
use App\Models\Accounting\CreditLimit;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CreditManagementTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.credit.view',
            'accounting.credit.manage',
            'accounting.credit.hold',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeContact(): Contact
    {
        return Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_CUSTOMER,
        ]);
    }

    private function makeLimit(Contact $contact, array $overrides = []): CreditLimit
    {
        return CreditLimit::create(array_merge([
            'organization_id'    => $this->organization->id,
            'contact_id'         => $contact->id,
            'credit_limit'       => 50000.00,
            'currency_code'      => 'SAR',
            'valid_from'         => '2025-01-01',
            'payment_terms_days' => 30,
            'risk_class'         => CreditLimit::RISK_LOW,
        ], $overrides));
    }

    private function makeHold(Contact $contact, array $overrides = []): CreditHold
    {
        return CreditHold::create(array_merge([
            'organization_id' => $this->organization->id,
            'contact_id'      => $contact->id,
            'held_at'         => now(),
            'hold_reason'     => 'Overdue invoices',
            'held_by'         => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Credit Limits — Index
    // -------------------------------------------------------------------------

    public function test_index_limits_returns_paginated_list(): void
    {
        $contact = $this->makeContact();
        $this->makeLimit($contact);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/credit-management/limits');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_limits_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/credit-management/limits');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Credit Limits — Store
    // -------------------------------------------------------------------------

    public function test_store_limit_creates_credit_limit(): void
    {
        $contact = $this->makeContact();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/credit-management/limits', [
                'contact_id'         => $contact->id,
                'credit_limit'       => 100000,
                'currency_code'      => 'SAR',
                'valid_from'         => '2025-01-01',
                'payment_terms_days' => 30,
                'risk_class'         => CreditLimit::RISK_LOW,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_limit_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/credit-management/limits', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Credit Limits — Update
    // -------------------------------------------------------------------------

    public function test_update_limit_modifies_credit_limit(): void
    {
        $contact = $this->makeContact();
        $limit   = $this->makeLimit($contact, ['credit_limit' => 50000]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/credit-management/limits/' . $limit->uuid, [
                'credit_limit' => 75000,
                'valid_from'   => '2025-01-01',
            ]);

        $response->assertStatus(200);
        $this->assertEquals(75000, $limit->fresh()->credit_limit);
    }

    // -------------------------------------------------------------------------
    // Credit Exposure
    // -------------------------------------------------------------------------

    public function test_show_exposure_returns_data_for_contact(): void
    {
        $contact = $this->makeContact();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/credit-management/exposure/contacts/' . $contact->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_index_exposure_snapshots_returns_list(): void
    {
        $contact = $this->makeContact();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/credit-management/exposure/contacts/' . $contact->uuid . '/snapshots');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_snapshot_exposures_runs_successfully(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/credit-management/exposure/snapshot', [
                'date' => '2025-01-31',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Credit Holds — Index
    // -------------------------------------------------------------------------

    public function test_index_holds_returns_paginated_list(): void
    {
        $contact = $this->makeContact();
        $this->makeHold($contact);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/credit-management/holds');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Credit Holds — Place
    // -------------------------------------------------------------------------

    public function test_place_hold_creates_hold(): void
    {
        $contact = $this->makeContact();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/credit-management/holds/contacts/' . $contact->uuid, [
                'hold_reason' => 'Past due balance',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_place_hold_validates_required_fields(): void
    {
        $contact = $this->makeContact();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/credit-management/holds/contacts/' . $contact->uuid, []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Credit Holds — Release
    // -------------------------------------------------------------------------

    public function test_release_hold_releases_active_hold(): void
    {
        $contact = $this->makeContact();
        $hold    = $this->makeHold($contact);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/credit-management/holds/' . $hold->uuid . '/release', [
                'release_reason' => 'Payment received',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotNull($hold->fresh()->released_at);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/credit-management/limits')->assertStatus(401);
    }
}
