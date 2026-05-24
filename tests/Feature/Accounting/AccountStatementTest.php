<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AccountStatementTest extends TestCase
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

    private function makeContact(array $overrides = []): Contact
    {
        return Contact::create(array_merge([
            'organization_id' => $this->organization->id,
            'contact_type'    => 'customer',
            'company_name'    => 'Test Company',
            'contact_name'    => 'Test Contact',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Customer Statement
    // -------------------------------------------------------------------------

    public function test_customer_statement_validates_from_required(): void
    {
        $contact = $this->makeContact();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/statements/customers/' . $contact->id);

        $response->assertStatus(422);
    }

    public function test_customer_statement_returns_data(): void
    {
        $contact = $this->makeContact();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/statements/customers/' . $contact->id . '?from=2025-01-01&to=2025-12-31');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Vendor Statement
    // -------------------------------------------------------------------------

    public function test_vendor_statement_validates_from_required(): void
    {
        $contact = $this->makeContact(['contact_type' => 'vendor']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/statements/vendors/' . $contact->id);

        $response->assertStatus(422);
    }

    public function test_vendor_statement_returns_data(): void
    {
        $contact = $this->makeContact(['contact_type' => 'vendor']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/statements/vendors/' . $contact->id . '?from=2025-01-01&to=2025-12-31');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Send Statement
    // -------------------------------------------------------------------------

    public function test_send_statement_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/statements/send', []);

        $response->assertStatus(422);
    }

    public function test_send_statement_validates_type_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/statements/send', [
                'contact_id' => 1,
                'type'       => 'invalid',
                'from'       => '2025-01-01',
                'to'         => '2025-12-31',
                'email'      => 'test@example.com',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Open Items
    // -------------------------------------------------------------------------

    public function test_open_items_validates_contact_id_required(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/statements/open-items');

        $response->assertStatus(422);
    }

    public function test_open_items_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/statements/open-items?contact_id=1&type=customer');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Confirm Reconciliation
    // -------------------------------------------------------------------------

    public function test_confirm_reconciliation_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/statements/confirm-reconciliation', []);

        $response->assertStatus(422);
    }

    public function test_confirm_reconciliation_returns_success(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/statements/confirm-reconciliation', [
                'contact_id'     => 1,
                'type'           => 'customer',
                'confirmed_date' => '2025-12-31',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/statements/customers/1?from=2025-01-01&to=2025-12-31')
            ->assertStatus(401);
    }
}
