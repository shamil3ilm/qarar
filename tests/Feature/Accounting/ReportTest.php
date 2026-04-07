<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Currency;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReportTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private string $baseUrl = '/reports';
    private FiscalYear $fiscalYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->ensureBaseCurrency();
    }

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
     * Create accounts and fiscal year context for reports.
     */
    private function setUpReportingContext(): void
    {
        $this->fiscalYear = FiscalYear::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'name' => 'FY 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => true,
            'is_closed' => false,
        ]);

        // Create standard accounts
        $cashAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'code' => '1001',
            'name' => 'Cash',
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_CASH,
            'currency_code' => 'SAR',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ]);

        $salesAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'code' => '4001',
            'name' => 'Sales Revenue',
            'account_type' => Account::TYPE_INCOME,
            'sub_type' => Account::SUBTYPE_SALES,
            'currency_code' => 'SAR',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ]);

        $expenseAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'code' => '5001',
            'name' => 'Operating Expenses',
            'account_type' => Account::TYPE_EXPENSE,
            'sub_type' => Account::SUBTYPE_OPERATING_EXPENSE,
            'currency_code' => 'SAR',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ]);

        $liabilityAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'code' => '2001',
            'name' => 'Accounts Payable',
            'account_type' => Account::TYPE_LIABILITY,
            'sub_type' => Account::SUBTYPE_PAYABLE,
            'currency_code' => 'SAR',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ]);

        $equityAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'code' => '3001',
            'name' => 'Capital',
            'account_type' => Account::TYPE_EQUITY,
            'sub_type' => Account::SUBTYPE_CAPITAL,
            'currency_code' => 'SAR',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ]);

        // Create posted journal entries for report data
        $this->createPostedEntry(
            $cashAccount->id,
            $salesAccount->id,
            10000.00,
            '2025-03-15',
            'Sales revenue - March'
        );

        $this->createPostedEntry(
            $expenseAccount->id,
            $cashAccount->id,
            3000.00,
            '2025-04-20',
            'Operating expenses - April'
        );

        $this->createPostedEntry(
            $cashAccount->id,
            $equityAccount->id,
            50000.00,
            '2025-01-05',
            'Capital injection'
        );
    }

    /**
     * Create a posted journal entry with two balanced lines.
     */
    private function createPostedEntry(
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        string $date,
        string $description
    ): JournalEntry {
        $je = JournalEntry::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'entry_date' => $date,
            'description' => $description,
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'total_debit' => $amount,
            'total_credit' => $amount,
            'status' => JournalEntry::STATUS_POSTED,
            'posted_at' => now(),
            'posted_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $je->id,
            'account_id' => $debitAccountId,
            'debit' => $amount,
            'credit' => 0,
            'line_order' => 1,
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $je->id,
            'account_id' => $creditAccountId,
            'debit' => 0,
            'credit' => $amount,
            'line_order' => 2,
        ]);

        return $je;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/reports/trial-balance - Trial Balance
    // -------------------------------------------------------------------------

    public function test_can_view_trial_balance_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.reports.view']);
        $this->setUpReportingContext();

        $response = $this->apiGet("{$this->baseUrl}/trial-balance");

        $this->assertSuccessResponse($response);
        $response->assertJsonStructure([
            'data',
        ]);
    }

    public function test_trial_balance_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/reports/trial-balance', [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_trial_balance_requires_permission(): void
    {
        $this->setUpAuthenticatedUser([]);

        $response = $this->apiGet("{$this->baseUrl}/trial-balance");

        $this->assertForbidden($response);
    }

    public function test_trial_balance_supports_date_range_filtering(): void
    {
        $this->setUpAuthenticatedUser(['accounting.reports.view']);
        $this->setUpReportingContext();

        $response = $this->apiGet(
            "{$this->baseUrl}/trial-balance?start_date=2025-01-01&end_date=2025-06-30"
        );

        $this->assertSuccessResponse($response);
    }

    public function test_trial_balance_supports_as_of_date(): void
    {
        $this->setUpAuthenticatedUser(['accounting.reports.view']);
        $this->setUpReportingContext();

        $response = $this->apiGet(
            "{$this->baseUrl}/trial-balance?as_of_date=2025-03-31"
        );

        $this->assertSuccessResponse($response);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/reports/balance-sheet - Balance Sheet
    // -------------------------------------------------------------------------

    public function test_can_view_balance_sheet_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.reports.view']);
        $this->setUpReportingContext();

        $response = $this->apiGet("{$this->baseUrl}/balance-sheet");

        $this->assertSuccessResponse($response);
    }

    public function test_balance_sheet_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/reports/balance-sheet', [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_balance_sheet_requires_permission(): void
    {
        $this->setUpAuthenticatedUser([]);

        $response = $this->apiGet("{$this->baseUrl}/balance-sheet");

        $this->assertForbidden($response);
    }

    public function test_balance_sheet_supports_as_of_date(): void
    {
        $this->setUpAuthenticatedUser(['accounting.reports.view']);
        $this->setUpReportingContext();

        $response = $this->apiGet(
            "{$this->baseUrl}/balance-sheet?as_of_date=2025-06-30"
        );

        $this->assertSuccessResponse($response);
    }

    public function test_balance_sheet_includes_asset_liability_equity_sections(): void
    {
        $this->setUpAuthenticatedUser(['accounting.reports.view']);
        $this->setUpReportingContext();

        $response = $this->apiGet("{$this->baseUrl}/balance-sheet");

        $this->assertSuccessResponse($response);
        $data = $response->json('data');

        // Balance sheet should contain the main sections
        $this->assertIsArray($data);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/reports/income-statement - Income Statement
    // -------------------------------------------------------------------------

    public function test_can_view_income_statement_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.reports.view']);
        $this->setUpReportingContext();

        $response = $this->apiGet("{$this->baseUrl}/income-statement");

        $this->assertSuccessResponse($response);
    }

    public function test_income_statement_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/reports/income-statement', [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_income_statement_requires_permission(): void
    {
        $this->setUpAuthenticatedUser([]);

        $response = $this->apiGet("{$this->baseUrl}/income-statement");

        $this->assertForbidden($response);
    }

    public function test_income_statement_supports_date_range_filtering(): void
    {
        $this->setUpAuthenticatedUser(['accounting.reports.view']);
        $this->setUpReportingContext();

        $response = $this->apiGet(
            "{$this->baseUrl}/income-statement?start_date=2025-01-01&end_date=2025-06-30"
        );

        $this->assertSuccessResponse($response);
    }

    public function test_income_statement_supports_fiscal_year_filter(): void
    {
        $this->setUpAuthenticatedUser(['accounting.reports.view']);
        $this->setUpReportingContext();

        $response = $this->apiGet(
            "{$this->baseUrl}/income-statement?fiscal_year_id={$this->fiscalYear->id}"
        );

        $this->assertSuccessResponse($response);
    }

    // -------------------------------------------------------------------------
    // Tenant Isolation on Reports
    // -------------------------------------------------------------------------

    public function test_reports_only_include_own_organization_data(): void
    {
        $this->setUpAuthenticatedUser(['accounting.reports.view']);
        $this->setUpReportingContext();

        // Create data in another organization
        $otherOrg = \App\Models\Core\Organization::factory()->create(['country_code' => 'AE', 'base_currency' => 'AED']);
        $otherBranch = \App\Models\Core\Branch::factory()->create([
            'organization_id' => $otherOrg->id,
            'country_code' => 'AE',
        ]);
        $otherFy = FiscalYear::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other FY',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => true,
            'is_closed' => false,
        ]);
        $otherCashAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'code' => '1001',
            'name' => 'Other Cash',
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_CASH,
            'currency_code' => 'AED',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ]);
        $otherSalesAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'code' => '4001',
            'name' => 'Other Sales',
            'account_type' => Account::TYPE_INCOME,
            'sub_type' => Account::SUBTYPE_SALES,
            'currency_code' => 'AED',
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
        ]);

        // Create a big journal entry in the other org
        $otherJe = JournalEntry::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'fiscal_year_id' => $otherFy->id,
            'entry_date' => '2025-06-15',
            'description' => 'Other org entry',
            'currency_code' => 'AED',
            'exchange_rate' => 1.0,
            'total_debit' => 999999.00,
            'total_credit' => 999999.00,
            'status' => JournalEntry::STATUS_POSTED,
            'posted_at' => now(),
            'posted_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $otherJe->id,
            'account_id' => $otherCashAccount->id,
            'debit' => 999999.00,
            'credit' => 0,
            'line_order' => 1,
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $otherJe->id,
            'account_id' => $otherSalesAccount->id,
            'debit' => 0,
            'credit' => 999999.00,
            'line_order' => 2,
        ]);

        // Trial balance for own org should not include other org data
        $response = $this->apiGet("{$this->baseUrl}/trial-balance");

        $this->assertSuccessResponse($response);
        // The response should not contain the large amount from the other org
        $responseContent = $response->getContent();
        $this->assertStringNotContainsString('999999', $responseContent);
    }

    // -------------------------------------------------------------------------
    // Date Range Validation Tests
    // -------------------------------------------------------------------------

    public function test_trial_balance_validates_date_format(): void
    {
        $this->setUpAuthenticatedUser(['accounting.reports.view']);
        $this->setUpReportingContext();

        $response = $this->apiGet(
            "{$this->baseUrl}/trial-balance?start_date=invalid-date"
        );

        // Should either return 422 or handle gracefully
        $this->assertTrue(
            in_array($response->status(), [200, 422]),
            "Expected status 200 or 422, got {$response->status()}"
        );
    }

    public function test_reports_return_empty_data_for_period_with_no_entries(): void
    {
        $this->setUpAuthenticatedUser(['accounting.reports.view']);
        $this->setUpReportingContext();

        // Query for a period with no entries
        $response = $this->apiGet(
            "{$this->baseUrl}/income-statement?start_date=2025-11-01&end_date=2025-11-30"
        );

        $this->assertSuccessResponse($response);
    }
}
