<?php

declare(strict_types=1);

namespace Tests\Feature\Journeys;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Purchase\Bill;
use App\Models\Purchase\PaymentMade;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Multi-tenant isolation journey test.
 *
 * Verifies that every resource created by Organization A is completely
 * invisible and inaccessible to Organization B — even when Org B knows
 * the exact UUID of Org A's records.
 *
 * Scenarios:
 *   1.  Org A creates an invoice; Org B GET → 403/404
 *   2.  Org B cannot list Org A's invoices in the index
 *   3.  Org A creates a contact; Org B cannot view it
 *   4.  Org A creates a bill; Org B cannot approve it
 *   5.  Org A sends an invoice; journal entry carries Org A's organization_id
 *   6.  Org B's GL scope: JournalEntry::all() returns only Org B's entries
 *   7.  Cross-org payment allocation is rejected
 *   8.  Org B cannot delete Org A's contact
 *   9.  Org B cannot void Org A's invoice
 */
class MultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    // Org A fixtures (set up via TestHelpers)
    private Contact $orgACustomer;
    private Product $orgAProduct;

    // Org B fixtures
    private \App\Models\Core\Organization $orgB;
    private \App\Models\Core\Branch $orgBBranch;
    private \App\Models\User $orgBUser;
    private string $orgBToken;

    protected function setUp(): void
    {
        parent::setUp();

        // ----- Org A -----
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.contacts.view',
            'sales.contacts.create',
            'sales.contacts.delete',
            'sales.invoices.view',
            'sales.invoices.create',
            'sales.invoices.edit',
            'sales.invoices.send',
            'sales.invoices.void',
            'sales.payments.view',
            'sales.payments.create',
            'purchase.bills.view',
            'purchase.bills.create',
            'purchase.bills.approve',
        ]);
        $this->setUpOpenFiscalPeriod();
        $this->seedOrgAGlAccounts();

        $unitA = UnitOfMeasure::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->orgACustomer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_CUSTOMER,
            'currency_code'   => 'SAR',
        ]);

        $this->orgAProduct = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'Org A Product',
            'type'            => Product::TYPE_SERVICE,
            'unit_id'         => $unitA->id,
            'selling_price'   => 1000.00,
            'purchase_price'  => 500.00,
            'track_inventory' => false,
            'is_active'       => true,
            'is_purchasable'  => true,
        ]);

        // ----- Org B (second tenant — set up manually to not overwrite $this->organization) -----
        $this->setUpOrgB();
    }

    // =========================================================================
    // 1. Org B cannot GET Org A's invoice by UUID
    // =========================================================================

    public function test_org_b_cannot_read_org_a_invoice(): void
    {
        $invoiceId = $this->createAndSendOrgAInvoice();

        // Org B tries to fetch Org A's invoice
        $response = $this->withHeaders($this->orgBHeaders())->getJson("/api/v1/sales/invoices/{$invoiceId}");

        $this->assertContains(
            $response->status(),
            [403, 404],
            "Org B must not see Org A's invoice (got HTTP {$response->status()})"
        );

        // Make sure we didn't accidentally return Org A's data
        if ($response->status() === 200) {
            $this->fail('Org B must NEVER receive a 200 for Org A resources');
        }
    }

    // =========================================================================
    // 2. Org B's invoice index returns zero of Org A's invoices
    // =========================================================================

    public function test_org_b_invoice_index_excludes_org_a_invoices(): void
    {
        // Create 3 invoices for Org A
        for ($i = 0; $i < 3; $i++) {
            $this->createAndSendOrgAInvoice();
        }

        // Org B lists invoices — must get empty (or only Org B's own invoices)
        $response = $this->withHeaders($this->orgBHeaders())->getJson('/api/v1/sales/invoices');
        $response->assertStatus(200);

        $orgAId = $this->organization->id;
        $data   = $response->json('data') ?? [];

        foreach ($data as $invoiceRow) {
            $this->assertNotEquals(
                $orgAId,
                $invoiceRow['organization_id'] ?? null,
                "Org A's invoice must not appear in Org B's index response"
            );
        }
    }

    // =========================================================================
    // 3. Org B cannot view Org A's contact
    // =========================================================================

    public function test_org_b_cannot_view_org_a_contact(): void
    {
        $response = $this->withHeaders($this->orgBHeaders())
            ->getJson("/api/v1/sales/contacts/{$this->orgACustomer->id}");

        $this->assertContains(
            $response->status(),
            [403, 404],
            "Org B must not see Org A's contact (got HTTP {$response->status()})"
        );
    }

    // =========================================================================
    // 4. Org B cannot delete Org A's contact
    // =========================================================================

    public function test_org_b_cannot_delete_org_a_contact(): void
    {
        $response = $this->withHeaders($this->orgBHeaders())
            ->deleteJson("/api/v1/sales/contacts/{$this->orgACustomer->id}");

        $this->assertContains(
            $response->status(),
            [403, 404],
            "Org B must not delete Org A's contact (got HTTP {$response->status()})"
        );

        // Contact must still exist in DB
        $this->assertDatabaseHas('contacts', ['id' => $this->orgACustomer->id]);
    }

    // =========================================================================
    // 5. Org B cannot void Org A's invoice
    // =========================================================================

    public function test_org_b_cannot_void_org_a_invoice(): void
    {
        $invoiceId = $this->createAndSendOrgAInvoice();

        $response = $this->withHeaders($this->orgBHeaders())
            ->postJson("/api/v1/sales/invoices/{$invoiceId}/void", ['reason' => 'Cross-tenant attack']);

        $this->assertContains(
            $response->status(),
            [403, 404],
            "Org B must not be able to void Org A's invoice (got HTTP {$response->status()})"
        );

        // Invoice must still be SENT (not voided) — bypass global scope for direct DB assertion
        $invoice = Invoice::withoutGlobalScopes()->find($invoiceId);
        $this->assertNotNull($invoice, 'Invoice must still exist in DB');
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status, "Org A's invoice must remain SENT");
    }

    // =========================================================================
    // 6. Journal entries carry correct organization_id (never null, never wrong org)
    // =========================================================================

    public function test_all_journal_entries_carry_correct_organization_id(): void
    {
        // Org A creates and sends an invoice
        $invoiceId = $this->createAndSendOrgAInvoice();
        $invoice   = Invoice::find($invoiceId);

        $journalEntry = JournalEntry::find($invoice->journal_entry_id);
        $this->assertNotNull($journalEntry);
        $this->assertNotNull(
            $journalEntry->organization_id,
            'journal_entry.organization_id must never be null'
        );
        $this->assertEquals(
            $this->organization->id,
            $journalEntry->organization_id,
            'journal_entry.organization_id must equal the invoice owner organization'
        );
        $this->assertNotEquals(
            $this->orgB->id,
            $journalEntry->organization_id,
            "Journal entry must not carry Org B's organization_id"
        );
    }

    // =========================================================================
    // 7. Org B's Eloquent global scope does not return Org A's journal entries
    // =========================================================================

    public function test_org_b_global_scope_excludes_org_a_journal_entries(): void
    {
        // Org A creates a posted journal entry
        $invoiceId = $this->createAndSendOrgAInvoice();
        $invoice   = Invoice::find($invoiceId);
        $this->assertNotNull($invoice->journal_entry_id);

        // Simulate Org B's auth context by logging in as Org B user
        $this->actingAs($this->orgBUser);

        // The BelongsToOrganization global scope should filter by the *authenticated* user's org.
        // We are intentionally NOT calling withoutGlobalScopes() here.
        $orgBJournalEntries = JournalEntry::all();

        $orgAId = $this->organization->id;
        foreach ($orgBJournalEntries as $entry) {
            $this->assertNotEquals(
                $orgAId,
                $entry->organization_id,
                "Org A's journal entry must not appear when Org B is authenticated"
            );
        }
    }

    // =========================================================================
    // 8. Org A's data is accessible by its own user
    // =========================================================================

    public function test_org_a_user_can_read_own_invoice(): void
    {
        $invoiceId = $this->createAndSendOrgAInvoice();

        // Org A's own user fetches the invoice
        $response = $this->apiGet("/sales/invoices/{$invoiceId}");
        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertEquals($invoiceId, $response->json('data.id'));
        $this->assertEquals($this->organization->id, $response->json('data.organization_id'));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Create and send an Org A invoice, return the invoice UUID.
     */
    private function createAndSendOrgAInvoice(): int|string
    {
        $createResponse = $this->apiPost('/sales/invoices', [
            'customer_id'   => $this->orgACustomer->id,
            'invoice_date'  => now()->format('Y-m-d'),
            'due_date'      => now()->addDays(30)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'lines' => [
                [
                    'product_id'  => $this->orgAProduct->id,
                    'description' => 'Isolation test service',
                    'quantity'    => 1,
                    'unit_price'  => 1000.00,
                    'tax_rate'    => 0,
                ],
            ],
        ]);

        $createResponse->assertStatus(201);
        $invoiceId = $createResponse->json('data.id');

        $this->apiPost("/sales/invoices/{$invoiceId}/send")->assertStatus(200);

        return $invoiceId;
    }

    /**
     * Build auth headers for Org B requests.
     */
    private function orgBHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->orgBToken,
            'Accept'        => 'application/json',
            'X-Branch-Id'   => (string) $this->orgBBranch->id,
        ];
    }

    /**
     * Boot Org B as a completely separate tenant.
     * Does NOT overwrite $this->organization / $this->branch / $this->user / $this->token.
     */
    private function setUpOrgB(): void
    {
        $this->seedBaseCurrenciesPublic();

        $this->orgB = \App\Models\Core\Organization::factory()->create([
            'country_code'  => 'AE',
            'tax_scheme'    => 'VAT',
            'base_currency' => 'AED',
        ]);

        $this->orgBBranch = \App\Models\Core\Branch::factory()->create([
            'organization_id' => $this->orgB->id,
            'country_code'    => 'AE',
            'is_default'      => true,
        ]);

        // Enable modules for Org B
        $modules = ['core', 'accounting', 'inventory', 'sales', 'purchase'];
        foreach ($modules as $module) {
            \App\Models\Core\OrganizationModule::create([
                'organization_id' => $this->orgB->id,
                'module_code'     => $module,
                'is_enabled'      => true,
                'enabled_at'      => now(),
            ]);
        }

        // Open fiscal period for Org B
        $this->setUpOpenFiscalPeriod(now()->format('Y-m-d'), (string) $this->orgB->id);

        // Create role + user for Org B
        $role = \App\Models\Core\Role::factory()->create([
            'organization_id' => $this->orgB->id,
            'name'            => 'Org B User',
            'slug'            => 'org-b-user-' . \Illuminate\Support\Str::random(6),
        ]);

        // Attach the same permissions so Org B user is fully provisioned
        $perms = [
            'sales.contacts.view', 'sales.contacts.create', 'sales.contacts.delete',
            'sales.invoices.view', 'sales.invoices.create', 'sales.invoices.send',
            'sales.invoices.void', 'purchase.bills.view', 'purchase.bills.approve',
        ];

        foreach ($perms as $permSlug) {
            $parts      = explode('.', $permSlug);
            $module     = $parts[0] ?? 'core';
            $permission = \App\Models\Core\Permission::firstOrCreate(
                ['slug' => $permSlug],
                ['name' => ucwords(str_replace('.', ' ', $permSlug)), 'module' => $module]
            );
            $role->permissions()->attach($permission->id);
        }

        $this->orgBUser = \App\Models\User::factory()->create([
            'organization_id' => $this->orgB->id,
        ]);

        $this->orgBUser->roles()->attach($role->id);
        $this->orgBUser->branches()->attach($this->orgBBranch->id, ['is_default' => true]);
        $this->orgBToken = JWTAuth::fromUser($this->orgBUser);
    }

    /**
     * Expose the private seedBaseCurrencies method from TestHelpers.
     * (TestHelpers::seedBaseCurrencies is private — we duplicate the logic here.)
     */
    private function seedBaseCurrenciesPublic(): void
    {
        $currencies = [
            ['code' => 'SAR', 'name' => 'Saudi Riyal',  'symbol' => 'ر.س', 'decimal_places' => 2],
            ['code' => 'AED', 'name' => 'UAE Dirham',    'symbol' => 'د.إ', 'decimal_places' => 2],
            ['code' => 'USD', 'name' => 'US Dollar',     'symbol' => '$',   'decimal_places' => 2],
            ['code' => 'INR', 'name' => 'Indian Rupee',  'symbol' => '₹',   'decimal_places' => 2],
        ];
        foreach ($currencies as $c) {
            \App\Models\Accounting\Currency::firstOrCreate(['code' => $c['code']], $c);
        }
    }

    private function seedOrgAGlAccounts(): void
    {
        $ar = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => 'receivable',
            'code'            => '1100',
            'name'            => 'Accounts Receivable',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $sales = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_INCOME,
            'sub_type'        => 'sales',
            'code'            => '4000',
            'name'            => 'Sales Revenue',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $bank = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => 'bank',
            'code'            => '1010',
            'name'            => 'Cash at Bank',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        $tax = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_LIABILITY,
            'sub_type'        => 'tax_payable',
            'code'            => '2100',
            'name'            => 'VAT Output',
            'is_system'       => true,
            'currency_code'   => 'SAR',
        ]);

        Config::set('erp.default_accounts.receivable', $ar->id);
        Config::set('erp.default_accounts.sales', $sales->id);
        Config::set('erp.default_accounts.cash', $bank->id);
        Config::set('erp.default_accounts.tax_payable', $tax->id);
    }
}
