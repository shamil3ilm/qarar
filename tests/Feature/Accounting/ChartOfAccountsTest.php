<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Currency;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private string $baseUrl = '/accounts';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->ensureBaseCurrency();
    }

    /**
     * Ensure the base currency record exists in the currencies table.
     */
    private function ensureBaseCurrency(): void
    {
        Currency::firstOrCreate(
            ['code' => 'SAR'],
            ['name' => 'Saudi Riyal', 'symbol' => 'SAR', 'decimal_places' => 2, 'is_active' => true]
        );
        Currency::firstOrCreate(
            ['code' => 'AED'],
            ['name' => 'UAE Dirham', 'symbol' => 'AED', 'decimal_places' => 2, 'is_active' => true]
        );
    }

    /**
     * Create a chart of accounts entry scoped to the current organization.
     */
    private function createAccount(array $overrides = []): Account
    {
        return Account::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $this->organization->id,
            'code' => '1' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'name' => 'Test Account',
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_CASH,
            'currency_code' => 'SAR',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ], $overrides));
    }

    /**
     * Create a fiscal year for the organization.
     */
    private function createFiscalYear(array $overrides = []): FiscalYear
    {
        return FiscalYear::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $this->organization->id,
            'name' => 'FY 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => true,
            'is_closed' => false,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/accounts - List Accounts (Tree)
    // -------------------------------------------------------------------------

    public function test_can_list_accounts_tree_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.view']);

        $parent = $this->createAccount([
            'code' => '1000',
            'name' => 'Assets',
            'is_header' => true,
            'parent_id' => null,
            'level' => 0,
        ]);

        $this->createAccount([
            'code' => '1100',
            'name' => 'Cash',
            'parent_id' => $parent->id,
            'level' => 1,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertSuccessResponse($response);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'code', 'name', 'account_type'],
            ],
        ]);
    }

    public function test_list_accounts_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/accounts', [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_list_accounts_requires_permission(): void
    {
        $this->setUpAuthenticatedUser([]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertForbidden($response);
    }

    public function test_list_accounts_respects_tenant_isolation(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.view']);

        // Create account for current org
        $ownAccount = $this->createAccount([
            'code' => '1000',
            'name' => 'Own Account',
        ]);

        // Create account for different org
        $otherOrg = Organization::factory()->create(['country_code' => 'AE', 'base_currency' => 'AED']);
        Account::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'code' => '1000',
            'name' => 'Other Org Account',
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_CASH,
            'currency_code' => 'AED',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertSuccessResponse($response);
        $data = $response->json('data');

        // Verify only own org accounts are returned
        $accountNames = collect($data)->pluck('name')->toArray();
        $this->assertContains('Own Account', $accountNames);
        $this->assertNotContains('Other Org Account', $accountNames);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/accounts/flat - Flat List
    // -------------------------------------------------------------------------

    public function test_can_list_accounts_flat_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.view']);

        $this->createAccount(['code' => '1000', 'name' => 'Assets']);
        $this->createAccount(['code' => '2000', 'name' => 'Liabilities', 'account_type' => Account::TYPE_LIABILITY]);

        $response = $this->apiGet($this->baseUrl . '/flat');

        $this->assertSuccessResponse($response);
    }

    public function test_flat_list_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/accounts/flat', [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/accounts - Create Account
    // -------------------------------------------------------------------------

    public function test_can_create_account_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.create']);

        $payload = [
            'code' => '1001',
            'name' => 'Petty Cash',
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_CASH,
            'currency_code' => 'SAR',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $response->assertJsonFragment(['code' => '1001', 'name' => 'Petty Cash']);

        $this->assertDatabaseHas('chart_of_accounts', [
            'organization_id' => $this->organization->id,
            'code' => '1001',
            'name' => 'Petty Cash',
        ]);
    }

    public function test_can_create_child_account_with_parent(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.create']);

        $parent = $this->createAccount([
            'code' => '1000',
            'name' => 'Assets',
            'is_header' => true,
            'level' => 0,
        ]);

        $payload = [
            'code' => '1100',
            'name' => 'Cash and Bank',
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_CASH,
            'currency_code' => 'SAR',
            'parent_id' => $parent->id,
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $response->assertJsonFragment(['parent_id' => $parent->id]);
    }

    public function test_create_account_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.view']);

        $response = $this->apiPost($this->baseUrl, [
            'code' => '1001',
            'name' => 'Test',
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_CASH,
            'currency_code' => 'SAR',
        ]);

        $this->assertForbidden($response);
    }

    public function test_create_account_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/accounts', [
            'code' => '1001',
            'name' => 'Test',
        ], ['Accept' => 'application/json']);

        $this->assertUnauthorized($response);
    }

    public function test_create_account_validates_required_fields(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.create']);

        $response = $this->apiPost($this->baseUrl, []);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_account_validates_account_type(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.create']);

        $response = $this->apiPost($this->baseUrl, [
            'code' => '1001',
            'name' => 'Test Account',
            'account_type' => 'invalid_type',
            'sub_type' => Account::SUBTYPE_CASH,
            'currency_code' => 'SAR',
        ]);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_account_rejects_duplicate_code_in_same_org(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.create']);

        $this->createAccount(['code' => '1001', 'name' => 'Existing Account']);

        $response = $this->apiPost($this->baseUrl, [
            'code' => '1001',
            'name' => 'Duplicate Code',
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_CASH,
            'currency_code' => 'SAR',
        ]);

        $this->assertErrorResponse($response, 422);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/accounts/{account} - Show Account
    // -------------------------------------------------------------------------

    public function test_can_show_account_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.view']);

        $account = $this->createAccount(['code' => '1001', 'name' => 'Bank Account']);

        $response = $this->apiGet("{$this->baseUrl}/{$account->id}");

        $this->assertSuccessResponse($response);
        $response->assertJsonFragment(['code' => '1001', 'name' => 'Bank Account']);
    }

    public function test_show_account_returns_404_for_nonexistent(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.view']);

        $response = $this->apiGet("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    public function test_show_account_enforces_tenant_isolation(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.view']);

        // Create account in a different organization
        $otherOrg = Organization::factory()->create(['country_code' => 'AE', 'base_currency' => 'AED']);
        $otherAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'code' => '1001',
            'name' => 'Other Org Account',
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_CASH,
            'currency_code' => 'AED',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$otherAccount->id}");

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/accounts/{account} - Update Account
    // -------------------------------------------------------------------------

    public function test_can_update_account_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.update']);

        $account = $this->createAccount(['code' => '1001', 'name' => 'Old Name']);

        $response = $this->apiPut("{$this->baseUrl}/{$account->id}", [
            'name' => 'Updated Name',
        ]);

        $this->assertSuccessResponse($response);
        $response->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_update_account_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.view']);

        $account = $this->createAccount(['code' => '1001']);

        $response = $this->apiPut("{$this->baseUrl}/{$account->id}", [
            'name' => 'Updated',
        ]);

        $this->assertForbidden($response);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/accounts/{account} - Delete Account
    // -------------------------------------------------------------------------

    public function test_can_delete_non_system_account_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.delete']);

        $account = $this->createAccount([
            'code' => '1999',
            'name' => 'Deletable Account',
            'is_system' => false,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$account->id}");

        $this->assertSuccessResponse($response);
    }

    public function test_cannot_delete_system_account(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.delete']);

        $systemAccount = $this->createAccount([
            'code' => '1000',
            'name' => 'System Account',
            'is_system' => true,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$systemAccount->id}");

        $this->assertErrorResponse($response);

        $this->assertDatabaseHas('chart_of_accounts', [
            'id' => $systemAccount->id,
        ]);
    }

    public function test_delete_account_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.view']);

        $account = $this->createAccount(['code' => '1999']);

        $response = $this->apiDelete("{$this->baseUrl}/{$account->id}");

        $this->assertForbidden($response);
    }

    public function test_cannot_delete_account_with_journal_entries(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.delete']);

        $account = $this->createAccount(['code' => '1001', 'name' => 'Account With Entries']);
        $fy = $this->createFiscalYear();

        // Create a journal entry with a line referencing this account
        $je = JournalEntry::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'fiscal_year_id' => $fy->id,
            'entry_date' => '2025-06-15',
            'description' => 'Test entry',
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'total_debit' => 1000,
            'total_credit' => 1000,
            'status' => JournalEntry::STATUS_POSTED,
            'created_by' => $this->user->id,
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $je->id,
            'account_id' => $account->id,
            'debit' => 1000,
            'credit' => 0,
            'line_order' => 1,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$account->id}");

        $this->assertErrorResponse($response);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/accounts/{account}/ledger - Account Ledger
    // -------------------------------------------------------------------------

    public function test_can_view_account_ledger_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.view']);

        $account = $this->createAccount(['code' => '1001', 'name' => 'Ledger Account']);

        $response = $this->apiGet("{$this->baseUrl}/{$account->id}/ledger");

        $this->assertSuccessResponse($response);
    }

    public function test_account_ledger_supports_date_filtering(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.view']);

        $account = $this->createAccount(['code' => '1001', 'name' => 'Ledger Account']);

        $response = $this->apiGet(
            "{$this->baseUrl}/{$account->id}/ledger?start_date=2025-01-01&end_date=2025-12-31"
        );

        $this->assertSuccessResponse($response);
    }

    // -------------------------------------------------------------------------
    // Hierarchical Structure Tests
    // -------------------------------------------------------------------------

    public function test_parent_child_relationship_preserved_in_tree(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.view']);

        $parent = $this->createAccount([
            'code' => '1000',
            'name' => 'Parent Assets',
            'is_header' => true,
            'parent_id' => null,
            'level' => 0,
        ]);

        $child = $this->createAccount([
            'code' => '1100',
            'name' => 'Child Cash',
            'parent_id' => $parent->id,
            'level' => 1,
        ]);

        $grandchild = $this->createAccount([
            'code' => '1110',
            'name' => 'Grandchild Petty Cash',
            'parent_id' => $child->id,
            'level' => 2,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertSuccessResponse($response);

        // Verify the hierarchical structure exists in the database
        $this->assertDatabaseHas('chart_of_accounts', [
            'id' => $child->id,
            'parent_id' => $parent->id,
        ]);
        $this->assertDatabaseHas('chart_of_accounts', [
            'id' => $grandchild->id,
            'parent_id' => $child->id,
        ]);
    }

    public function test_cannot_delete_parent_with_children(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.delete']);

        $parent = $this->createAccount([
            'code' => '1000',
            'name' => 'Parent Account',
            'is_header' => true,
            'parent_id' => null,
            'level' => 0,
        ]);

        $this->createAccount([
            'code' => '1100',
            'name' => 'Child Account',
            'parent_id' => $parent->id,
            'level' => 1,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$parent->id}");

        $this->assertErrorResponse($response);
    }

    // -------------------------------------------------------------------------
    // Account Type Tests
    // -------------------------------------------------------------------------

    public function test_can_create_each_account_type(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.create']);

        $types = [
            Account::TYPE_ASSET => Account::SUBTYPE_CASH,
            Account::TYPE_LIABILITY => Account::SUBTYPE_PAYABLE,
            Account::TYPE_EQUITY => Account::SUBTYPE_CAPITAL,
            Account::TYPE_INCOME => Account::SUBTYPE_SALES,
            Account::TYPE_EXPENSE => Account::SUBTYPE_OPERATING_EXPENSE,
        ];

        $code = 1000;
        foreach ($types as $type => $subType) {
            $response = $this->apiPost($this->baseUrl, [
                'code' => (string) $code,
                'name' => ucfirst($type) . ' Account',
                'account_type' => $type,
                'sub_type' => $subType,
                'currency_code' => 'SAR',
            ]);

            $this->assertCreatedResponse($response);
            $code += 1000;
        }
    }
}
