<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ContactTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/sales/contacts';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.contacts.view',
            'sales.contacts.create',
            'sales.contacts.edit',
            'sales.contacts.delete',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | List Contacts
    |--------------------------------------------------------------------------
    */

    public function test_can_list_contacts(): void
    {
        Contact::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
    }

    public function test_list_contacts_returns_only_own_organization(): void
    {
        // Contacts for current organization
        Contact::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
        ]);

        // Contacts for another organization (should not appear)
        $otherOrg = Organization::factory()->create();
        Contact::factory()->count(3)->create([
            'organization_id' => $otherOrg->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $contact) {
            $this->assertEquals($this->organization->id, $contact['organization_id']);
        }
    }

    public function test_list_contacts_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1{$this->baseUrl}", [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_can_filter_contacts_by_customer_type(): void
    {
        Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);
        Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);
        Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_BOTH,
        ]);

        $response = $this->apiGet("{$this->baseUrl}?contact_type=customer");

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');

        foreach ($data as $contact) {
            $this->assertContains($contact['contact_type'], [Contact::TYPE_CUSTOMER, Contact::TYPE_BOTH]);
        }
    }

    public function test_can_filter_contacts_by_supplier_type(): void
    {
        Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);
        Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
        ]);

        $response = $this->apiGet("{$this->baseUrl}?contact_type=supplier");

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');

        foreach ($data as $contact) {
            $this->assertContains($contact['contact_type'], [Contact::TYPE_SUPPLIER, Contact::TYPE_BOTH]);
        }
    }

    public function test_can_search_contacts_by_name(): void
    {
        Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'company_name' => 'Acme Trading LLC',
        ]);
        Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'company_name' => 'Beta Industries',
        ]);

        $response = $this->apiGet("{$this->baseUrl}?search=Acme");

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertStringContainsString('Acme', $data[0]['company_name']);
    }

    public function test_can_search_contacts_by_email(): void
    {
        Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'info@acme-trading.com',
        ]);

        $response = $this->apiGet("{$this->baseUrl}?search=acme-trading");

        $this->assertPaginatedResponse($response);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Contact
    |--------------------------------------------------------------------------
    */

    public function test_can_create_customer_contact(): void
    {
        $payload = [
            'contact_type' => Contact::TYPE_CUSTOMER,
            'company_name' => 'Test Customer LLC',
            'contact_name' => 'John Doe',
            'email' => 'john@testcustomer.com',
            'phone' => '+966501234567',
            'tax_number' => '300000000000003',
            'currency_code' => 'SAR',
            'payment_terms' => 30,
            'credit_limit' => 50000.00,
            'billing_address_line_1' => '123 Main Street',
            'billing_city' => 'Riyadh',
            'billing_country_code' => 'SA',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $data = $response->json('data');
        $this->assertEquals('customer', $data['contact_type']);
        $this->assertEquals('Test Customer LLC', $data['company_name']);
        $this->assertEquals('john@testcustomer.com', $data['email']);
        $this->assertEquals($this->organization->id, $data['organization_id']);
    }

    public function test_can_create_supplier_contact(): void
    {
        $payload = [
            'contact_type' => Contact::TYPE_SUPPLIER,
            'company_name' => 'Parts Supplier Inc',
            'email' => 'orders@partssupplier.com',
            'tax_number' => '300000000000004',
            'currency_code' => 'SAR',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $this->assertEquals('supplier', $response->json('data.contact_type'));
    }

    public function test_can_create_contact_of_type_both(): void
    {
        $payload = [
            'contact_type' => Contact::TYPE_BOTH,
            'company_name' => 'Dual Partner Trading',
            'email' => 'info@dualpartner.com',
            'tax_number' => '300000000000005',
            'currency_code' => 'SAR',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $this->assertEquals('both', $response->json('data.contact_type'));
    }

    public function test_create_contact_requires_authentication(): void
    {
        $response = $this->postJson("/api/v1{$this->baseUrl}", [
            'contact_type' => Contact::TYPE_CUSTOMER,
            'company_name' => 'Test',
        ], ['Accept' => 'application/json']);

        $this->assertUnauthorized($response);
    }

    public function test_create_contact_validates_required_fields(): void
    {
        $response = $this->apiPost($this->baseUrl, []);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_contact_validates_contact_type(): void
    {
        $payload = [
            'contact_type' => 'invalid_type',
            'company_name' => 'Test',
            'email' => 'test@test.com',
            'currency_code' => 'SAR',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_contact_validates_email_format(): void
    {
        $payload = [
            'contact_type' => Contact::TYPE_CUSTOMER,
            'company_name' => 'Test',
            'email' => 'not-an-email',
            'currency_code' => 'SAR',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_contact_validates_unique_email_per_organization(): void
    {
        Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'duplicate@test.com',
        ]);

        $payload = [
            'contact_type' => Contact::TYPE_CUSTOMER,
            'company_name' => 'Duplicate Test',
            'email' => 'duplicate@test.com',
            'currency_code' => 'SAR',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Contact
    |--------------------------------------------------------------------------
    */

    public function test_can_show_contact(): void
    {
        $contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'company_name' => 'Show Test LLC',
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$contact->id}");

        $this->assertSuccessResponse($response);
        $this->assertEquals('Show Test LLC', $response->json('data.company_name'));
    }

    public function test_cannot_show_contact_from_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $contact = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$contact->id}");

        $response->assertStatus(404);
    }

    public function test_show_nonexistent_contact_returns_404(): void
    {
        $response = $this->apiGet("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Contact
    |--------------------------------------------------------------------------
    */

    public function test_can_update_contact(): void
    {
        $contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'company_name' => 'Original Name',
        ]);

        $response = $this->apiPut("{$this->baseUrl}/{$contact->id}", [
            'company_name' => 'Updated Name',
        ]);

        $this->assertSuccessResponse($response);
        $this->assertEquals('Updated Name', $response->json('data.company_name'));
    }

    public function test_cannot_update_contact_from_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $contact = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $response = $this->apiPut("{$this->baseUrl}/{$contact->id}", [
            'company_name' => 'Hacked Name',
        ]);

        $response->assertStatus(404);
    }

    public function test_update_contact_validates_email_format(): void
    {
        $contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->apiPut("{$this->baseUrl}/{$contact->id}", [
            'email' => 'not-valid',
        ]);

        $this->assertErrorResponse($response, 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Contact
    |--------------------------------------------------------------------------
    */

    public function test_can_delete_contact(): void
    {
        $contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$contact->id}");

        $this->assertSuccessResponse($response);
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_cannot_delete_contact_from_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $contact = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$contact->id}");

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Contact Statement and Balance
    |--------------------------------------------------------------------------
    */

    public function test_can_get_contact_statement(): void
    {
        $contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$contact->id}/statement");

        $this->assertSuccessResponse($response);
    }

    public function test_can_get_contact_balance(): void
    {
        $contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$contact->id}/balance");

        $this->assertSuccessResponse($response);
    }
}
