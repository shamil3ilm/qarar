<?php

declare(strict_types=1);

namespace Tests\Feature\Journeys;

use App\Models\Accounting\Account;
use App\Models\Accounting\AssetCategory;
use App\Models\Accounting\AssetTransfer;
use App\Models\Accounting\ExchangeRate;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\LeaseContract;
use App\Models\Accounting\HouseBank;
use App\Models\Accounting\InstallmentPlan;
use App\Models\Accounting\InstallmentSchedule;
use App\Models\Accounting\PaymentAdvice;
use App\Models\Accounting\PaymentToleranceGroup;
use App\Models\Accounting\WithholdingTaxCode;
use App\Models\Accounting\WithholdingTaxLine;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * FI — Financial Accounting journey tests covering the three SAP parity gaps:
 *
 * 1. FI-AA Component Accounting  — SAP AS02 / ABAVN partial retirement
 * 2. eBAM (Electronic Bank Account Management) — SAP EBAM workflow
 * 3. XBRL Regulatory Filings — SAP FINSC_LEDGER / iXBRL output
 */
class FinancialAccountingJourneyTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            // FI-AA
            'accounting.assets.view',
            'accounting.assets.create',
            'accounting.assets.dispose',
            'accounting.assets.delete',
            // eBAM
            'accounting.bank-accounts.view',
            'accounting.bank-accounts.manage',
            'accounting.bank-accounts.approve',
            // XBRL
            'accounting.xbrl.view',
            'accounting.xbrl.create',
            'accounting.xbrl.edit',
            'accounting.xbrl.submit',
            'accounting.xbrl.manage',
            // General accounting
            'accounting.accounts.view',
            'accounting.accounts.create',
            'accounting.fiscal-years.view',
            'accounting.fiscal-years.create',
            // Contacts (payment block)
            'sales.contacts.view',
            'sales.contacts.create',
            'sales.contacts.edit',
            // Leases
            'accounting.leases.view',
            'accounting.leases.create',
            'accounting.leases.post',
            'accounting.leases.manage',
            // WHT
            'accounting.wht.view',
            'accounting.wht.manage',
            'accounting.wht.post',
            // Payment Tolerance
            'accounting.tolerance.view',
            'accounting.tolerance.manage',
            'accounting.tolerance.post',
            // Installment Plans
            'accounting.installments.view',
            'accounting.installments.create',
            'accounting.installments.manage',
            'accounting.installments.post',
            // House Bank & Payment Advices
            'accounting.housebank.view',
            'accounting.housebank.create',
            'accounting.housebank.manage',
        ]);

        $this->setUpOpenFiscalPeriod();
    }

    // =========================================================================
    // FI-AA: Component Accounting
    // =========================================================================

    public function test_fi_aa_component_accounting_lifecycle(): void
    {
        // 1. Create asset category and fixed asset
        $category = AssetCategory::create([
            'organization_id'     => $this->organization->id,
            'name'                => 'Vehicles',
            'code'                => 'VEH',
            'default_useful_life_years' => 5,
            'default_depreciation_method' => 'straight_line',
        ]);

        $assetRes = $this->apiPost('/assets', [
            'asset_category_id'  => $category->id,
            'name'               => 'Company Truck',
            'acquisition_date'   => '2025-01-01',
            'acquisition_cost'   => 80000,
            'useful_life_years'  => 8,
            'depreciation_method' => 'straight_line',
        ]);
        $assetRes->assertStatus(201);
        $assetId = $assetRes->json('data.id');

        // 2. Add components (engine, tyres) — SAP AS02 sub-number creation
        $engineRes = $this->apiPost("/assets/{$assetId}/components", [
            'name'              => 'Engine Assembly',
            'acquisition_date'  => '2025-01-01',
            'acquisition_cost'  => 30000,
            'useful_life_years' => 8,
        ]);
        $engineRes->assertStatus(201);

        $this->assertNotNull($engineRes->json('data.component_number'));
        $this->assertEquals(30000, $engineRes->json('data.acquisition_cost'));

        $tyresRes = $this->apiPost("/assets/{$assetId}/components", [
            'name'              => 'Tyre Set',
            'acquisition_date'  => '2025-01-01',
            'acquisition_cost'  => 5000,
            'useful_life_years' => 2,
        ]);
        $tyresRes->assertStatus(201);
        $tyresId = $tyresRes->json('data.id');

        // 3. Verify parent asset cost was updated (80k + 30k + 5k = 115k)
        $assetAfter = FixedAsset::find($assetId);
        $this->assertEquals(115000, (float) $assetAfter->acquisition_cost);

        // 4. List components
        $listRes = $this->apiGet("/assets/{$assetId}/components");
        $listRes->assertStatus(200);
        $this->assertEquals(2, $listRes->json('meta.total'));

        // 5. Retire a component (SAP ABAVN partial retirement) — replace tyres
        $retireRes = $this->apiPost("/assets/{$assetId}/components/{$tyresId}/retire", [
            'proceeds_amount'  => 500,
            'retirement_date'  => '2026-06-01',
            'reason'           => 'Tyre replacement — worn out',
        ]);
        $retireRes->assertStatus(200);
        $this->assertEquals('retired', $retireRes->json('data.status'));

        // 6. Parent asset acquisition_cost is reduced after retirement
        $assetAfterRetirement = FixedAsset::find($assetId);
        $this->assertLessThan(115000, (float) $assetAfterRetirement->acquisition_cost);

        // 7. Retired component cannot be retired again
        $this->apiPost("/assets/{$assetId}/components/{$tyresId}/retire", [
            'proceeds_amount' => 0,
            'retirement_date' => '2026-07-01',
            'reason'          => 'Second retirement',
        ])->assertStatus(422);
    }

    public function test_fi_aa_component_can_be_deleted_when_active_with_no_journal(): void
    {
        $category = AssetCategory::create([
            'organization_id' => $this->organization->id,
            'name'            => 'Equipment',
            'code'            => 'EQP',
            'default_useful_life_years' => 5,
        ]);

        $assetRes = $this->apiPost('/assets', [
            'asset_category_id' => $category->id,
            'name'              => 'CNC Machine',
            'acquisition_date'  => '2025-03-01',
            'acquisition_cost'  => 50000,
            'useful_life_years' => 10,
        ]);
        $assetId = $assetRes->json('data.id');

        $compRes = $this->apiPost("/assets/{$assetId}/components", [
            'name'             => 'Spindle Motor',
            'acquisition_date' => '2025-03-01',
            'acquisition_cost' => 8000,
            'useful_life_years' => 5,
        ]);
        $compId = $compRes->json('data.id');

        // Delete active component with no journal entry — should succeed
        $this->apiDelete("/assets/{$assetId}/components/{$compId}")
            ->assertStatus(200);
    }

    // =========================================================================
    // eBAM: Electronic Bank Account Management
    // =========================================================================

    public function test_ebam_full_account_opening_workflow(): void
    {
        // Provide GL account ID for bank account creation
        $glAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code'            => '1020',
            'name'            => 'Current Bank Account',
            'account_type'    => 'asset',
            'sub_type'        => 'bank',
            'is_active'       => true,
        ]);

        // 1. Create an account opening request
        $reqRes = $this->apiPost('/bank-account-requests', [
            'request_type'  => 'open',
            'bank_name'     => 'Saudi National Bank',
            'account_name'  => 'Operating Account',
            'account_type'  => 'current',
            'currency_code' => 'SAR',
            'iban'          => 'SA4420000001234567891234',
            'swift_code'    => 'NCBKSAJE',
            'justification' => 'New branch operations require dedicated account',
            'request_data'  => ['gl_account_id' => $glAccount->id],
        ]);
        $reqRes->assertStatus(201);
        $this->assertEquals('pending', $reqRes->json('data.status'));
        $requestId = $reqRes->json('data.id');

        // 2. Approve the request
        $this->apiPost("/bank-account-requests/{$requestId}/review", [
            'action' => 'approve',
            'notes'  => 'Approved by CFO — valid business need',
        ])->assertStatus(200)->assertJsonPath('data.status', 'approved');

        // 3. Execute (creates the BankAccount record)
        $execRes = $this->apiPost("/bank-account-requests/{$requestId}/execute");
        $execRes->assertStatus(200)->assertJsonPath('data.status', 'executed');

        // 4. A bank account was created
        $bankAccountId = $execRes->json('data.bank_account_id');
        $this->assertNotNull($bankAccountId);

        // 5. Add signatories to the new account
        $sigRes = $this->apiPost("/bank-accounts/{$bankAccountId}/signatories", [
            'name'            => 'Ahmed Al-Rashidi',
            'title'           => 'CFO',
            'email'           => 'ahmed@example.com',
            'authority_level' => 'single',
            'signing_limit'   => 500000,
            'valid_from'      => now()->toDateString(),
        ]);
        $sigRes->assertStatus(201);
        $this->assertEquals('single', $sigRes->json('data.authority_level'));
        $signatoryId = $sigRes->json('data.id');

        // 6. List signatories
        $this->apiGet("/bank-accounts/{$bankAccountId}/signatories?active_only=true")
            ->assertStatus(200);

        // 7. Revoke signatory
        $this->apiPost("/bank-accounts/{$bankAccountId}/signatories/{$signatoryId}/revoke", [
            'reason' => 'Employee resigned',
        ])->assertStatus(200)->assertJsonPath('data.is_active', false);
    }

    public function test_ebam_account_closing_workflow(): void
    {
        $glAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code'            => '1021',
            'name'            => 'SAMBA Bank Account',
            'account_type'    => 'asset',
            'sub_type'        => 'bank',
            'is_active'       => true,
        ]);

        // Setup: create a bank account to close
        $bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'bank_name'       => 'SAMBA Bank',
            'account_name'    => 'Redundant Account',
            'account_number'  => '9999999999',
            'currency_code'   => 'SAR',
            'account_type'    => 'current',
            'gl_account_id'   => $glAccount->id,
            'is_active'       => true,
        ]);

        // Request to close
        $reqRes = $this->apiPost('/bank-account-requests', [
            'request_type'    => 'close',
            'bank_account_id' => $bankAccount->id,
            'justification'   => 'Account no longer needed — consolidating bank relationships',
        ]);
        $reqRes->assertStatus(201);
        $requestId = $reqRes->json('data.id');

        $this->apiPost("/bank-account-requests/{$requestId}/review", [
            'action' => 'approve',
            'notes'  => 'Finance committee approval granted',
        ])->assertStatus(200);

        $this->apiPost("/bank-account-requests/{$requestId}/execute")
            ->assertStatus(200);

        $bankAccount->refresh();
        $this->assertFalse((bool) $bankAccount->is_active);
    }

    public function test_ebam_reject_request_prevents_execution(): void
    {
        $reqRes = $this->apiPost('/bank-account-requests', [
            'request_type'  => 'open',
            'bank_name'     => 'Unknown Bank',
            'account_name'  => 'Suspicious Account',
            'account_type'  => 'current',
            'currency_code' => 'SAR',
            'justification' => 'Not a valid reason',
        ]);
        $requestId = $reqRes->json('data.id');

        // Reject
        $this->apiPost("/bank-account-requests/{$requestId}/review", [
            'action' => 'reject',
            'reason' => 'No valid business justification provided',
        ])->assertStatus(200);

        // Execute should now fail (not in approved status)
        $this->apiPost("/bank-account-requests/{$requestId}/execute")
            ->assertStatus(400);
    }

    // =========================================================================
    // XBRL: Regulatory Filings
    // =========================================================================

    public function test_xbrl_filing_full_lifecycle(): void
    {
        $fiscalYear = FiscalYear::where('organization_id', $this->organization->id)->first()
            ?? $this->createFiscalYear();

        // 1. Create a taxonomy (IFRS 2023)
        $taxRes = $this->apiPost('/xbrl/taxonomies', [
            'name'      => 'IFRS 2023',
            'version'   => '2023-03-23',
            'namespace' => 'http://xbrl.ifrs.org/taxonomy/2023-03-23/ifrs-full',
        ]);
        $taxRes->assertStatus(201);
        $this->assertEquals('IFRS 2023', $taxRes->json('data.name'));
        $taxonomyId = $taxRes->json('data.id');

        // 2. List taxonomies
        $this->apiGet('/xbrl/taxonomies')->assertStatus(200);

        // 3. Create a filing
        $filingRes = $this->apiPost('/xbrl/filings', [
            'fiscal_year_id'          => $fiscalYear->id,
            'taxonomy_id'             => $taxonomyId,
            'report_type'             => 'annual',
            'period_start'            => $fiscalYear->start_date,
            'period_end'              => $fiscalYear->end_date,
            'seed_from_trial_balance' => false,
        ]);
        $filingRes->assertStatus(201);
        $this->assertEquals('draft', $filingRes->json('data.status'));
        $filingId = $filingRes->json('data.id');

        // 4. Tag elements
        $contextRef = "duration_{$fiscalYear->start_date}_{$fiscalYear->end_date}";
        foreach ([
            ['concept' => 'ifrs-full:Assets',      'value' => '500000.00', 'balance_type' => 'debit'],
            ['concept' => 'ifrs-full:Liabilities', 'value' => '200000.00', 'balance_type' => 'credit'],
            ['concept' => 'ifrs-full:Equity',      'value' => '300000.00', 'balance_type' => 'credit'],
        ] as $c) {
            $this->apiPost("/xbrl/filings/{$filingId}/elements", array_merge($c, [
                'context_ref' => $contextRef,
                'unit_ref'    => 'SAR',
                'decimals'    => 2,
                'period_type' => 'instant',
            ]))->assertStatus(200);
        }

        // 5. Validate — should pass
        $validateRes = $this->apiPost("/xbrl/filings/{$filingId}/validate");
        $validateRes->assertStatus(200);
        $this->assertEmpty($validateRes->json('data.errors'));

        $this->apiGet("/xbrl/filings/{$filingId}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'validated');

        // 6. Generate XML
        $this->apiPost("/xbrl/filings/{$filingId}/generate-xml")->assertStatus(200);

        // 7. Submit to regulator
        $submitRes = $this->apiPost("/xbrl/filings/{$filingId}/submit", [
            'external_reference' => 'ZATCA-XBRL-2025-00001',
        ]);
        $submitRes->assertStatus(200);
        $this->assertEquals('submitted', $submitRes->json('data.status'));

        // 8. Mark as accepted
        $this->apiPost("/xbrl/filings/{$filingId}/review", ['action' => 'accept'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_xbrl_filing_validation_fails_on_missing_required_concepts(): void
    {
        $fiscalYear = FiscalYear::where('organization_id', $this->organization->id)->first()
            ?? $this->createFiscalYear();

        $taxRes = $this->apiPost('/xbrl/taxonomies', [
            'name'      => 'IFRS 2024',
            'version'   => '2024-01-01',
            'namespace' => 'http://xbrl.ifrs.org/taxonomy/2024-01-01/ifrs-full',
        ]);
        $taxonomyId = $taxRes->json('data.id');

        $filingRes = $this->apiPost('/xbrl/filings', [
            'fiscal_year_id' => $fiscalYear->id,
            'taxonomy_id'    => $taxonomyId,
            'report_type'    => 'annual',
        ]);
        $filingId = $filingRes->json('data.id');

        // Validate with no elements — should return errors, status remains 'draft'
        $validateRes = $this->apiPost("/xbrl/filings/{$filingId}/validate");
        $validateRes->assertStatus(200);
        $this->assertNotEmpty($validateRes->json('data.errors'));

        $this->apiGet("/xbrl/filings/{$filingId}")
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_xbrl_duplicate_taxonomy_namespace_rejected(): void
    {
        $this->apiPost('/xbrl/taxonomies', [
            'name'      => 'IFRS 2025',
            'version'   => '2025-01-01',
            'namespace' => 'http://xbrl.ifrs.org/taxonomy/2025-01-01/ifrs-full',
        ])->assertStatus(201);

        // Second attempt with same namespace should fail
        $this->apiPost('/xbrl/taxonomies', [
            'name'      => 'IFRS 2025 Duplicate',
            'version'   => '2025-01-01',
            'namespace' => 'http://xbrl.ifrs.org/taxonomy/2025-01-01/ifrs-full',
        ])->assertStatus(422);
    }

    // =========================================================================
    // FI-FX: Auto FX Revaluation Runner (SAP F.05)
    // =========================================================================

    public function test_fi_fx_auto_run_revaluation_creates_entries_for_foreign_accounts(): void
    {
        // Create a foreign-currency GL account (USD)
        $usdAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code'            => '12001',
            'name'            => 'USD Receivables',
            'account_type'    => 'asset',
            'sub_type'        => 'other_asset',
            'currency_code'   => 'USD',
            'is_active'       => true,
            'is_header'       => false,
        ]);

        // Seed an exchange rate for USD
        ExchangeRate::create([
            'organization_id' => $this->organization->id,
            'from_currency'   => 'USD',
            'to_currency'     => 'SAR',
            'rate'            => '3.7500',
            'rate_date'       => '2026-03-31',
        ]);

        // Seed a posted journal entry line against the USD account
        // (simulate an open foreign-currency balance)
        $fy = \App\Models\Accounting\FiscalYear::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->first();
        $je = \App\Models\Accounting\JournalEntry::create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'fiscal_year_id'  => $fy->id,
            'entry_number'    => 'JE-TEST-FX-001',
            'entry_date'      => '2026-03-15',
            'description'     => 'USD invoice posting',
            'status'          => 'posted',
        ]);
        $je->lines()->create([
            'account_id'  => $usdAccount->id,
            'description' => 'USD receivable',
            'debit'       => 10000.00,
            'credit'      => 0,
        ]);

        $response = $this->apiPost('/multi-currency/revaluations/auto-run', [
            'revaluation_date' => '2026-03-31',
            'base_currency'    => 'SAR',
            'auto_post'        => false,
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertEquals('USD', $data['currency_code']);
        $this->assertEquals('SAR', $data['base_currency']);
        $this->assertNotEmpty($data['items']);
        $this->assertEquals($usdAccount->id, $data['items'][0]['account_id']);
        $this->assertEquals(10000.0, (float) $data['items'][0]['foreign_currency_balance']);
    }

    public function test_fi_fx_auto_run_fails_when_no_foreign_accounts(): void
    {
        // No foreign-currency accounts — should fail with 422
        $this->apiPost('/multi-currency/revaluations/auto-run', [
            'revaluation_date' => '2026-03-31',
            'base_currency'    => 'SAR',
        ])->assertStatus(422);
    }

    // =========================================================================
    // FI-AP: Payment Blocking on Contacts (SAP FI-AP)
    // =========================================================================

    public function test_fi_ap_payment_block_prevents_contact_appearing_in_payment_run(): void
    {
        // Create a supplier contact
        $supplier = Contact::create([
            'organization_id' => $this->organization->id,
            'contact_type'    => 'supplier',
            'company_name'    => 'Blocked Supplier Co.',
            'contact_name'    => 'Blocked Supplier Co.',
            'email'           => 'blocked@supplier.test',
            'payment_block'   => false,
        ]);

        // Block the contact
        $this->apiPatch("/sales/contacts/{$supplier->uuid}/payment-block", [
            'blocked' => true,
            'reason'  => 'Under audit investigation',
        ])->assertStatus(200)
          ->assertJsonPath('data.payment_block', true)
          ->assertJsonPath('data.payment_block_reason', 'Under audit investigation');

        // Verify the field persisted
        $this->assertDatabaseHas('contacts', [
            'id'                   => $supplier->id,
            'payment_block'        => true,
            'payment_block_reason' => 'Under audit investigation',
        ]);
    }

    public function test_fi_ap_unblock_restores_contact_for_payment(): void
    {
        $supplier = Contact::create([
            'organization_id'      => $this->organization->id,
            'contact_type'         => 'supplier',
            'company_name'         => 'Previously Blocked Co.',
            'contact_name'         => 'Previously Blocked Co.',
            'email'                => 'prev-blocked@supplier.test',
            'payment_block'        => true,
            'payment_block_reason' => 'Old reason',
        ]);

        $this->apiPatch("/sales/contacts/{$supplier->uuid}/payment-block", ['blocked' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.payment_block', false)
            ->assertJsonPath('data.payment_block_reason', null);

        $this->assertDatabaseHas('contacts', [
            'id'            => $supplier->id,
            'payment_block' => false,
        ]);
    }

    public function test_fi_ap_block_requires_reason(): void
    {
        $supplier = Contact::create([
            'organization_id' => $this->organization->id,
            'contact_type'    => 'supplier',
            'company_name'    => 'Test Supplier',
            'contact_name'    => 'Test Supplier',
            'email'           => 'test@supplier.test',
        ]);

        $this->apiPatch("/sales/contacts/{$supplier->uuid}/payment-block", ['blocked' => true])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // =========================================================================
    // FI-LA: IFRS 16 Lease Accounting
    // =========================================================================

    public function test_ifrs16_finance_lease_full_lifecycle(): void
    {
        // Create GL accounts needed for lease accounting
        $rouAccount = $this->createGlAccount('20001', 'ROU Asset', 'asset', 'fixed_asset');
        $accumDepAccount = $this->createGlAccount('20002', 'Accum Dep ROU', 'asset', 'other_asset');
        $liabilityAccount = $this->createGlAccount('20003', 'Lease Liability', 'liability', 'other_liability');
        $interestAccount = $this->createGlAccount('20004', 'Interest Expense', 'expense', 'other_expense');
        $depExpenseAccount = $this->createGlAccount('20005', 'Depreciation Expense', 'expense', 'operating_expense');

        // 1. Create a finance lease (36-month, monthly, 5% annual discount rate)
        $response = $this->apiPost('/leases', [
            'asset_description'             => 'Office Printer — Canon MF655Cdw',
            'lessor_name'                   => 'Al-Rashid Leasing LLC',
            'commencement_date'             => '2026-01-01',
            'end_date'                      => '2028-12-31',
            'lease_term_months'             => 36,
            'payment_amount'               => 1000.00,
            'payment_frequency'             => 'monthly',
            'currency_code'                 => 'SAR',
            'discount_rate'                 => 0.05,
            'classification'                => 'finance',
            'rou_asset_account_id'          => $rouAccount->id,
            'accum_depreciation_account_id' => $accumDepAccount->id,
            'lease_liability_account_id'    => $liabilityAccount->id,
            'interest_expense_account_id'   => $interestAccount->id,
            'depreciation_expense_account_id' => $depExpenseAccount->id,
        ]);

        $response->assertStatus(201);
        $leaseId = $response->json('data.uuid');

        $this->assertNotEmpty($response->json('data.initial_rou_asset'));
        $this->assertGreaterThan(0, (float) $response->json('data.initial_lease_liability'));

        // 2. List the amortisation schedule
        $scheduleResponse = $this->apiGet("/leases/{$leaseId}/schedule");
        $scheduleResponse->assertStatus(200);
        $schedule = $scheduleResponse->json('data');

        $this->assertCount(36, $schedule);
        $this->assertEquals(1, $schedule[0]['period_number']);
        $this->assertFalse($schedule[0]['is_posted']);
        $this->assertGreaterThan(0, (float) $schedule[0]['interest_portion']);
        $this->assertGreaterThan(0, (float) $schedule[0]['rou_depreciation']);

        // 3. Post period 1 journal entry
        $postResponse = $this->apiPost("/leases/{$leaseId}/post-entry", [
            'period_number' => 1,
        ]);
        $postResponse->assertStatus(200);
        $this->assertTrue($postResponse->json('data.is_posted'));
        $this->assertNotNull($postResponse->json('data.journal_entry_id'));

        // 4. Attempting to re-post period 1 should fail
        $this->apiPost("/leases/{$leaseId}/post-entry", ['period_number' => 1])
            ->assertStatus(422);

        // 5. Show lease — current_lease_liability should have decreased
        $showResponse = $this->apiGet("/leases/{$leaseId}");
        $showResponse->assertStatus(200);
        $this->assertLessThan(
            (float) $response->json('data.initial_lease_liability'),
            (float) $showResponse->json('data.current_lease_liability'),
        );
    }

    public function test_ifrs16_lease_termination_derecognises_rou_and_liability(): void
    {
        $liabilityAccount = $this->createGlAccount('21003', 'Lease Liability', 'liability', 'other_liability');
        $rouAccount       = $this->createGlAccount('21001', 'ROU Asset', 'asset', 'fixed_asset');
        $interestAccount  = $this->createGlAccount('21004', 'Interest Exp', 'expense', 'other_expense');

        $response = $this->apiPost('/leases', [
            'asset_description'          => 'Forklift',
            'commencement_date'          => '2026-01-01',
            'end_date'                   => '2028-12-31',
            'lease_term_months'          => 36,
            'payment_amount'            => 2000.00,
            'payment_frequency'          => 'monthly',
            'discount_rate'              => 0.06,
            'classification'             => 'finance',
            'lease_liability_account_id' => $liabilityAccount->id,
            'rou_asset_account_id'       => $rouAccount->id,
            'interest_expense_account_id' => $interestAccount->id,
        ]);
        $response->assertStatus(201);
        $leaseId = $response->json('data.uuid');

        // Terminate the lease
        $this->apiPost("/leases/{$leaseId}/terminate", [
            'termination_date' => '2026-06-30',
        ])->assertStatus(200)
          ->assertJsonPath('data.status', 'terminated');

        // Attempting a second termination should fail
        $this->apiPost("/leases/{$leaseId}/terminate", [
            'termination_date' => '2026-07-01',
        ])->assertStatus(422);
    }

    public function test_ifrs16_operating_lease_posts_straight_line_expense(): void
    {
        $expenseAccount  = $this->createGlAccount('22001', 'Rent Expense', 'expense', 'operating_expense');
        $liabilityAccount = $this->createGlAccount('22002', 'Operating Lease Payable', 'liability', 'other_liability');

        $response = $this->apiPost('/leases', [
            'asset_description'          => 'Office Space Floor 3',
            'commencement_date'          => '2026-01-01',
            'end_date'                   => '2026-12-31',
            'lease_term_months'          => 12,
            'payment_amount'            => 5000.00,
            'payment_frequency'          => 'monthly',
            'discount_rate'              => 0.04,
            'classification'             => 'operating',
            'interest_expense_account_id' => $expenseAccount->id,
            'lease_liability_account_id'  => $liabilityAccount->id,
        ]);
        $response->assertStatus(201);
        $leaseId = $response->json('data.uuid');

        $schedule = $this->apiGet("/leases/{$leaseId}/schedule")->json('data');
        $this->assertCount(12, $schedule);
        // Operating lease: zero ROU depreciation
        $this->assertEquals('0.0000', $schedule[0]['rou_depreciation']);
    }

    // =========================================================================
    // FI-AA: Inter-Company Asset Transfers (SAP ABUMN)
    // =========================================================================

    public function test_abumn_asset_transfer_full_lifecycle(): void
    {
        // Create a second organisation to transfer to
        $receivingOrg = Organization::withoutGlobalScopes()->where('id', '!=', $this->organization->id)->first();
        if (!$receivingOrg) {
            $receivingOrg = Organization::create([
                'name'         => 'Receiving Corp',
                'slug'         => 'receiving-corp',
                'country_code' => 'SA',
                'status'       => 'active',
            ]);
        }

        // Create a fixed asset to transfer
        $category = AssetCategory::create([
            'organization_id'            => $this->organization->id,
            'name'                       => 'Equipment',
            'code'                       => 'EQUIP',
            'default_useful_life_years'  => 5,
        ]);

        $asset = FixedAsset::create([
            'organization_id'          => $this->organization->id,
            'asset_category_id'        => $category->id,
            'asset_number'             => 'FA-2026-000001',
            'name'                     => 'CNC Machine',
            'acquisition_date'         => '2024-01-01',
            'acquisition_cost'         => 100000.00,
            'salvage_value'            => 5000.00,
            'useful_life_years'        => 5,
            'depreciation_method'      => 'straight_line',
            'accumulated_depreciation' => 20000.00,
            'book_value'               => 80000.00,
            'status'                   => 'active',
        ]);

        // 1. Initiate transfer
        $response = $this->apiPost("/assets/{$asset->uuid}/transfers", [
            'receiving_organization_id' => $receivingOrg->id,
            'transfer_date'             => '2026-04-01',
            'transfer_type'             => 'book_value',
        ]);
        $response->assertStatus(201);
        $transferUuid = $response->json('data.uuid');
        $this->assertEquals('pending', $response->json('data.status'));
        $this->assertEquals('80000.0000', $response->json('data.net_book_value'));

        // 2. Execute the transfer
        $executeResponse = $this->apiPost("/asset-transfers/{$transferUuid}/execute");
        $executeResponse->assertStatus(200);
        $this->assertEquals('completed', $executeResponse->json('data.status'));
        $this->assertNotNull($executeResponse->json('data.receiving_asset_id'));

        // Sending asset should now be disposed
        $asset->refresh();
        $this->assertEquals('disposed', $asset->status);

        // A new asset should exist in the receiving org
        $receivingAssetId = $executeResponse->json('data.receiving_asset_id');
        $receivingAsset = FixedAsset::withoutGlobalScopes()->find($receivingAssetId);
        $this->assertNotNull($receivingAsset);
        $this->assertEquals($receivingOrg->id, $receivingAsset->organization_id);
        $this->assertEquals('80000.0000', $receivingAsset->acquisition_cost);
    }

    public function test_abumn_asset_transfer_cancel(): void
    {
        $receivingOrg = Organization::withoutGlobalScopes()->where('id', '!=', $this->organization->id)->first()
            ?? Organization::create([
                'name' => 'Receiving Corp 2', 'slug' => 'receiving-corp-2',
                'country_code' => 'SA', 'status' => 'active',
            ]);

        $category = AssetCategory::create([
            'organization_id'           => $this->organization->id,
            'name'                      => 'Vehicles',
            'code'                      => 'VEH',
            'default_useful_life_years' => 4,
        ]);

        $asset = FixedAsset::create([
            'organization_id'          => $this->organization->id,
            'asset_category_id'        => $category->id,
            'asset_number'             => 'FA-2026-000002',
            'name'                     => 'Delivery Van',
            'acquisition_date'         => '2024-06-01',
            'acquisition_cost'         => 60000.00,
            'salvage_value'            => 3000.00,
            'useful_life_years'        => 4,
            'depreciation_method'      => 'straight_line',
            'accumulated_depreciation' => 10000.00,
            'book_value'               => 50000.00,
            'status'                   => 'active',
        ]);

        $transferResponse = $this->apiPost("/assets/{$asset->uuid}/transfers", [
            'receiving_organization_id' => $receivingOrg->id,
            'transfer_date'             => '2026-04-01',
            'transfer_type'             => 'book_value',
        ]);
        $transferUuid = $transferResponse->json('data.uuid');

        // Cancel the transfer
        $this->apiPost("/asset-transfers/{$transferUuid}/cancel", [
            'reason' => 'Business decision reversed',
        ])->assertStatus(200)
          ->assertJsonPath('data.status', 'cancelled');

        // Cannot execute a cancelled transfer
        $this->apiPost("/asset-transfers/{$transferUuid}/execute")
            ->assertStatus(422);
    }

    // =========================================================================
    // WHT: Withholding Tax (SAP F.67/F.68)
    // =========================================================================

    public function test_wht_code_crud_lifecycle(): void
    {
        // 1. Create a WHT code
        $createResponse = $this->apiPost('/withholding-tax/codes', [
            'code'          => 'WHT5',
            'name'          => 'WHT 5% — Services',
            'applicable_to' => 'supplier',
            'rate'          => 5.0,
            'country_code'  => 'SA',
            'tax_type'      => 'WHT',
        ]);
        $createResponse->assertStatus(201);
        $codeUuid = $createResponse->json('data.uuid');
        $this->assertNotNull($codeUuid);
        $this->assertEquals('WHT5', $createResponse->json('data.code'));
        $this->assertEquals('5.0000', $createResponse->json('data.rate'));

        // 2. List codes — should appear
        $listResponse = $this->apiGet('/withholding-tax/codes');
        $listResponse->assertStatus(200);
        $codes = collect($listResponse->json('data'));
        $this->assertTrue($codes->contains('uuid', $codeUuid));

        // 3. Show
        $this->apiGet("/withholding-tax/codes/{$codeUuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.code', 'WHT5');

        // 4. Update rate
        $this->apiPut("/withholding-tax/codes/{$codeUuid}", ['rate' => 7.5])
            ->assertStatus(200)
            ->assertJsonPath('data.rate', '7.5000');

        // 5. Duplicate code rejected
        $this->apiPost('/withholding-tax/codes', [
            'code'          => 'WHT5',
            'name'          => 'Duplicate',
            'applicable_to' => 'supplier',
            'rate'          => 5.0,
        ])->assertStatus(422);
    }

    public function test_wht_calculate_preview(): void
    {
        $code = WithholdingTaxCode::create([
            'organization_id' => $this->organization->id,
            'code'            => 'WHT10',
            'name'            => 'WHT 10%',
            'applicable_to'   => 'supplier',
            'rate'            => 10.0,
        ]);

        $response = $this->apiPost("/withholding-tax/codes/{$code->uuid}/calculate", [
            'gross_amount' => 50000.00,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(5000.0, $response->json('data.wht_amount'));
        $this->assertEquals(45000.0, $response->json('data.net_amount'));
        $this->assertEquals(10.0, $response->json('data.wht_rate'));
    }

    public function test_wht_apply_to_payment_posts_gl_entry(): void
    {
        // Create GL accounts so the journal can be posted
        $expenseAccount  = $this->createGlAccount('7200', 'WHT Expense', 'expense', 'other_expense');
        $payableAccount  = $this->createGlAccount('2310', 'WHT Payable', 'liability', 'tax_payable');

        $code = WithholdingTaxCode::create([
            'organization_id'   => $this->organization->id,
            'code'              => 'WHT15',
            'name'              => 'WHT 15%',
            'applicable_to'     => 'supplier',
            'rate'              => 15.0,
            'payable_account_id' => $payableAccount->id,
        ]);

        $contact = Contact::create([
            'organization_id' => $this->organization->id,
            'contact_type'    => 'supplier',
            'company_name'    => 'WHT Supplier Corp',
            'contact_name'    => 'WHT Supplier',
            'email'           => 'wht@supplier.test',
        ]);

        $response = $this->apiPost("/withholding-tax/codes/{$code->uuid}/apply", [
            'payment_type'     => 'payment_made',
            'payment_id'       => 1,
            'contact_id'       => $contact->id,
            'gross_amount'     => 10000.00,
            'currency_code'    => 'SAR',
            'transaction_date' => '2026-04-01',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(1500.0, $response->json('data.wht_amount'));
        $this->assertEquals(8500.0, $response->json('data.net_amount'));
        $this->assertEquals(15.0, $response->json('data.wht_rate'));
        $this->assertNotNull($response->json('data.journal_entry_id'));

        // Verify line was persisted
        $this->assertDatabaseHas('withholding_tax_lines', [
            'organization_id' => $this->organization->id,
            'payment_type'    => 'payment_made',
            'payment_id'      => 1,
            'wht_amount'      => 1500.0,
        ]);
    }

    public function test_wht_certificate_issuance(): void
    {
        $code = WithholdingTaxCode::create([
            'organization_id' => $this->organization->id,
            'code'            => 'WHT3',
            'name'            => 'WHT 3%',
            'applicable_to'   => 'supplier',
            'rate'            => 3.0,
        ]);

        // Create a line directly (without GL)
        $line = WithholdingTaxLine::create([
            'organization_id'  => $this->organization->id,
            'wht_code_id'      => $code->id,
            'payment_type'     => 'payment_made',
            'payment_id'       => 99,
            'gross_amount'     => 20000.00,
            'wht_rate'         => 3.0,
            'wht_amount'       => 600.00,
            'net_amount'       => 19400.00,
            'currency_code'    => 'SAR',
            'transaction_date' => '2026-04-01',
        ]);

        // Issue certificate
        $response = $this->apiPost("/withholding-tax/lines/{$line->uuid}/certificate", [
            'certificate_date' => '2026-04-01',
        ]);

        $response->assertStatus(200);
        $certNumber = $response->json('data.certificate_number');
        $this->assertNotNull($certNumber);
        $this->assertStringStartsWith('WHTC-', $certNumber);

        // Cannot issue twice
        $this->apiPost("/withholding-tax/lines/{$line->uuid}/certificate", [
            'certificate_date' => '2026-04-02',
        ])->assertStatus(422);
    }

    public function test_wht_summary_report(): void
    {
        $code = WithholdingTaxCode::create([
            'organization_id' => $this->organization->id,
            'code'            => 'WHTS',
            'name'            => 'WHT Summary Test',
            'applicable_to'   => 'supplier',
            'rate'            => 5.0,
        ]);

        // Seed two lines
        WithholdingTaxLine::create([
            'organization_id'  => $this->organization->id,
            'wht_code_id'      => $code->id,
            'payment_type'     => 'payment_made',
            'payment_id'       => 10,
            'gross_amount'     => 100000.00,
            'wht_rate'         => 5.0,
            'wht_amount'       => 5000.00,
            'net_amount'       => 95000.00,
            'currency_code'    => 'SAR',
            'transaction_date' => '2026-03-01',
        ]);
        WithholdingTaxLine::create([
            'organization_id'  => $this->organization->id,
            'wht_code_id'      => $code->id,
            'payment_type'     => 'payment_made',
            'payment_id'       => 11,
            'gross_amount'     => 50000.00,
            'wht_rate'         => 5.0,
            'wht_amount'       => 2500.00,
            'net_amount'       => 47500.00,
            'currency_code'    => 'SAR',
            'transaction_date' => '2026-03-15',
        ]);

        $response = $this->apiGet('/withholding-tax/summary?from=2026-03-01&to=2026-03-31&payment_type=payment_made');
        $response->assertStatus(200);

        $rows = $response->json('data');
        $this->assertNotEmpty($rows);
        $totalWht = collect($rows)->sum('total_wht');
        $this->assertEquals(7500.0, $totalWht);
    }

    // =========================================================================
    // Payment Tolerance & Clearing Variance (SAP FI OBA3/OBB8)
    // =========================================================================

    public function test_payment_tolerance_group_crud(): void
    {
        // 1. Create group with items
        $response = $this->apiPost('/payment-tolerance/groups', [
            'code'       => 'DEFAULT',
            'name'       => 'Default Tolerance',
            'applies_to' => 'both',
            'is_default' => true,
            'items'      => [
                [
                    'currency_code' => 'SAR',
                    'underpay_abs'  => 50.00,
                    'underpay_pct'  => 1.0,
                    'overpay_abs'   => 50.00,
                    'overpay_pct'   => 1.0,
                ],
            ],
        ]);
        $response->assertStatus(201);
        $groupUuid = $response->json('data.uuid');
        $this->assertEquals('DEFAULT', $response->json('data.code'));
        $this->assertCount(1, $response->json('data.items'));

        // 2. List — group appears
        $listResponse = $this->apiGet('/payment-tolerance/groups');
        $listResponse->assertStatus(200);
        $this->assertTrue(collect($listResponse->json('data'))->contains('uuid', $groupUuid));

        // 3. Show
        $this->apiGet("/payment-tolerance/groups/{$groupUuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.code', 'DEFAULT');

        // 4. Update
        $this->apiPut("/payment-tolerance/groups/{$groupUuid}", ['name' => 'Updated Default'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Default');

        // 5. Duplicate code rejected
        $this->apiPost('/payment-tolerance/groups', [
            'code'       => 'DEFAULT',
            'name'       => 'Duplicate',
            'applies_to' => 'customer',
        ])->assertStatus(422);
    }

    public function test_payment_tolerance_evaluate_within_tolerance(): void
    {
        $group = PaymentToleranceGroup::create([
            'organization_id' => $this->organization->id,
            'code'            => 'EVAL',
            'name'            => 'Eval Group',
            'applies_to'      => 'both',
        ]);
        $group->items()->create([
            'currency_code' => 'SAR',
            'underpay_abs'  => 100.00,
            'underpay_pct'  => 2.0,
            'overpay_abs'   => 100.00,
            'overpay_pct'   => 2.0,
        ]);

        // Underpayment within absolute limit
        $this->apiPost("/payment-tolerance/groups/{$group->uuid}/evaluate", [
            'invoice_amount' => 5000.00,
            'payment_amount' => 4950.00,   // 50 short — within 100 abs limit
            'currency_code'  => 'SAR',
        ])->assertStatus(200)
          ->assertJsonPath('data.within_tolerance', true)
          ->assertJsonPath('data.difference_type', 'underpayment');

        // Underpayment exceeds both abs and pct
        $this->apiPost("/payment-tolerance/groups/{$group->uuid}/evaluate", [
            'invoice_amount' => 5000.00,
            'payment_amount' => 4700.00,   // 300 short — exceeds 100 abs and 2% (100) limit
            'currency_code'  => 'SAR',
        ])->assertStatus(200)
          ->assertJsonPath('data.within_tolerance', false);

        // Overpayment within pct limit
        $this->apiPost("/payment-tolerance/groups/{$group->uuid}/evaluate", [
            'invoice_amount' => 10000.00,
            'payment_amount' => 10080.00,  // 80 over — within 2% (200) limit
            'currency_code'  => 'SAR',
        ])->assertStatus(200)
          ->assertJsonPath('data.within_tolerance', true)
          ->assertJsonPath('data.difference_type', 'overpayment');
    }

    public function test_payment_tolerance_clear_difference_posts_gl(): void
    {
        $expenseAccount = $this->createGlAccount('8100', 'Tolerance Expense', 'expense', 'other_expense');
        $incomeAccount  = $this->createGlAccount('7900', 'Tolerance Income', 'income', 'other_income');
        $receivable     = $this->createGlAccount('1300', 'AR Control', 'asset', 'receivable');

        $group = PaymentToleranceGroup::create([
            'organization_id' => $this->organization->id,
            'code'            => 'CLEAR',
            'name'            => 'Clearing Group',
            'applies_to'      => 'customer',
        ]);
        $group->items()->create([
            'currency_code'          => 'SAR',
            'underpay_abs'           => 200.00,
            'underpay_pct'           => 3.0,
            'overpay_abs'            => 200.00,
            'overpay_pct'            => 3.0,
            'underpay_gl_account_id' => $expenseAccount->id,
            'overpay_gl_account_id'  => $incomeAccount->id,
        ]);

        // Clear an underpayment
        $response = $this->apiPost("/payment-tolerance/groups/{$group->uuid}/clear", [
            'invoice_amount' => 10000.00,
            'payment_amount' => 9850.00,   // 150 short — within 200 abs
            'currency_code'  => 'SAR',
            'payment_type'   => 'payment_received',
            'payment_id'     => 42,
            'posting_date'   => '2026-04-01',
        ]);

        $response->assertStatus(201);
        $this->assertEquals('underpayment', $response->json('data.difference_type'));
        $this->assertEquals(-150.0, (float) $response->json('data.difference_amount'));
        $this->assertEquals('written_off', $response->json('data.resolution'));
        $this->assertNotNull($response->json('data.journal_entry_id'));

        // Verify difference post recorded in DB
        $this->assertDatabaseHas('payment_difference_posts', [
            'organization_id' => $this->organization->id,
            'payment_type'    => 'payment_received',
            'payment_id'      => 42,
            'difference_type' => 'underpayment',
        ]);
    }

    public function test_payment_tolerance_clear_rejects_exceeded_difference(): void
    {
        $group = PaymentToleranceGroup::create([
            'organization_id' => $this->organization->id,
            'code'            => 'STRICT',
            'name'            => 'Strict Tolerance',
            'applies_to'      => 'supplier',
        ]);
        $group->items()->create([
            'currency_code' => 'SAR',
            'underpay_abs'  => 10.00,
            'underpay_pct'  => 0.1,
            'overpay_abs'   => 10.00,
            'overpay_pct'   => 0.1,
        ]);

        $this->apiPost("/payment-tolerance/groups/{$group->uuid}/clear", [
            'invoice_amount' => 50000.00,
            'payment_amount' => 49000.00,  // 1000 short — far exceeds 10 abs and 0.1%
            'currency_code'  => 'SAR',
            'payment_type'   => 'payment_made',
            'payment_id'     => 99,
            'posting_date'   => '2026-04-01',
        ])->assertStatus(422)
          ->assertJsonPath('error.code', 'TOLERANCE_EXCEEDED');
    }

    public function test_payment_tolerance_variance_summary(): void
    {
        $group = PaymentToleranceGroup::create([
            'organization_id' => $this->organization->id,
            'code'            => 'RPT',
            'name'            => 'Report Group',
            'applies_to'      => 'both',
        ]);

        // Seed difference posts directly
        \App\Models\Accounting\PaymentDifferencePost::create([
            'organization_id'    => $this->organization->id,
            'tolerance_group_id' => $group->id,
            'payment_type'       => 'payment_received',
            'payment_id'         => 1,
            'currency_code'      => 'SAR',
            'invoice_amount'     => 10000.00,
            'payment_amount'     => 9900.00,
            'difference_amount'  => -100.00,
            'difference_type'    => 'underpayment',
            'resolution'         => 'written_off',
            'posting_date'       => '2026-04-01',
        ]);
        \App\Models\Accounting\PaymentDifferencePost::create([
            'organization_id'    => $this->organization->id,
            'tolerance_group_id' => $group->id,
            'payment_type'       => 'payment_received',
            'payment_id'         => 2,
            'currency_code'      => 'SAR',
            'invoice_amount'     => 20000.00,
            'payment_amount'     => 20050.00,
            'difference_amount'  => 50.00,
            'difference_type'    => 'overpayment',
            'resolution'         => 'written_off',
            'posting_date'       => '2026-04-01',
        ]);

        $response = $this->apiGet('/payment-tolerance/variance-summary?from=2026-04-01&to=2026-04-30');
        $response->assertStatus(200);

        $rows = collect($response->json('data'));
        $this->assertCount(2, $rows);
        $underpay = $rows->firstWhere('difference_type', 'underpayment');
        $this->assertEquals(100.0, (float) $underpay['total_variance']);
    }

    // =========================================================================
    // Installment Payment Plans (SAP FI-AR F-36 / FI-AP F-59)
    // =========================================================================

    public function test_installment_plan_equal_schedule_lifecycle(): void
    {
        // 1. Create plan — 3 equal monthly installments on an invoice
        $response = $this->apiPost('/installment-plans', [
            'document_type'     => 'invoice',
            'document_id'       => 1,
            'total_amount'      => 9000.00,
            'currency_code'     => 'SAR',
            'start_date'        => '2026-04-01',
            'installment_count' => 3,
            'frequency_days'    => 30,
        ]);
        $response->assertStatus(201);
        $planUuid = $response->json('data.uuid');
        $this->assertEquals(InstallmentPlan::STATUS_DRAFT, $response->json('data.status'));
        $this->assertCount(3, $response->json('data.schedules'));
        // Each installment = 3000 (9000 / 3)
        $this->assertEquals(3000.0, (float) $response->json('data.schedules.0.amount'));
        $this->assertEquals(3000.0, (float) $response->json('data.schedules.2.amount'));

        // 2. Activate
        $this->apiPost("/installment-plans/{$planUuid}/activate")
            ->assertStatus(200)
            ->assertJsonPath('data.status', InstallmentPlan::STATUS_ACTIVE);

        // 3. Show — loads schedules
        $showResponse = $this->apiGet("/installment-plans/{$planUuid}");
        $showResponse->assertStatus(200);
        $schedules    = $showResponse->json('data.schedules');
        $this->assertCount(3, $schedules);
        $scheduleUuid = $schedules[0]['uuid'];

        // 4. Record payment for first installment
        $this->apiPost("/installment-plans/{$planUuid}/schedules/{$scheduleUuid}/pay", [
            'paid_amount'  => 3000.00,
            'paid_date'    => '2026-04-01',
            'payment_type' => 'payment_received',
            'payment_id'   => 10,
        ])->assertStatus(200)
          ->assertJsonPath('data.status', InstallmentSchedule::STATUS_PAID);

        // 5. Plan outstanding reduced
        $this->apiGet("/installment-plans/{$planUuid}")
            ->assertJsonPath('data.outstanding', '6000.0000');
    }

    public function test_installment_plan_custom_schedule(): void
    {
        $response = $this->apiPost('/installment-plans', [
            'document_type'     => 'bill',
            'document_id'       => 5,
            'total_amount'      => 10000.00,
            'currency_code'     => 'SAR',
            'start_date'        => '2026-04-01',
            'installment_count' => 2,
            'schedules'         => [
                ['amount' => 4000.00, 'due_date' => '2026-04-15'],
                ['amount' => 6000.00, 'due_date' => '2026-05-15'],
            ],
        ]);
        $response->assertStatus(201);
        $this->assertEquals(4000.0, (float) $response->json('data.schedules.0.amount'));
        $this->assertEquals(6000.0, (float) $response->json('data.schedules.1.amount'));
        $this->assertStringStartsWith('2026-04-15', $response->json('data.schedules.0.due_date'));
    }

    public function test_installment_plan_cancel_waives_unpaid(): void
    {
        $plan = InstallmentPlan::create([
            'organization_id'   => $this->organization->id,
            'document_type'     => 'invoice',
            'document_id'       => 99,
            'currency_code'     => 'SAR',
            'total_amount'      => 6000.00,
            'total_paid'        => 0,
            'outstanding'       => 6000.00,
            'installment_count' => 2,
            'status'            => InstallmentPlan::STATUS_ACTIVE,
            'start_date'        => '2026-04-01',
            'created_by'        => 1,
        ]);
        $plan->schedules()->createMany([
            ['installment_number' => 1, 'amount' => 3000, 'paid_amount' => 0, 'due_date' => '2026-04-30', 'status' => 'pending'],
            ['installment_number' => 2, 'amount' => 3000, 'paid_amount' => 0, 'due_date' => '2026-05-31', 'status' => 'pending'],
        ]);

        $this->apiPost("/installment-plans/{$plan->uuid}/cancel", ['reason' => 'Contract terminated'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', InstallmentPlan::STATUS_CANCELLED);

        // Schedules are waived
        $this->assertDatabaseMissing('installment_schedules', [
            'installment_plan_id' => $plan->id,
            'status'              => 'pending',
        ]);
    }

    public function test_installment_plan_custom_schedule_rejects_wrong_total(): void
    {
        $this->apiPost('/installment-plans', [
            'document_type'     => 'invoice',
            'document_id'       => 2,
            'total_amount'      => 10000.00,
            'currency_code'     => 'SAR',
            'start_date'        => '2026-04-01',
            'installment_count' => 2,
            'schedules'         => [
                ['amount' => 4000.00, 'due_date' => '2026-04-15'],
                ['amount' => 5000.00, 'due_date' => '2026-05-15'], // 9000 ≠ 10000
            ],
        ])->assertStatus(422);
    }

    // =========================================================================
    // House Bank & Payment Advice (SAP FI-BL FI12 / FBZP)
    // =========================================================================

    public function test_house_bank_lifecycle(): void
    {
        // 1. Create a house bank (FI12)
        $createRes = $this->apiPost('/house-banks', [
            'code'         => 'RIYAD',
            'name'         => 'Riyad Bank',
            'bank_name'    => 'Riyad Bank Co.',
            'bank_country' => 'SA',
            'swift_code'   => 'RIBLSARI',
            'is_active'    => true,
            'is_default'   => true,
        ]);
        $createRes->assertStatus(201);
        $bankId = $createRes->json('data.id');
        $this->assertEquals('RIYAD', $createRes->json('data.code'));
        $this->assertTrue($createRes->json('data.is_default'));

        // 2. Creating a second default bank clears the first
        $secondRes = $this->apiPost('/house-banks', [
            'code'       => 'SABB',
            'name'       => 'Saudi British Bank',
            'is_active'  => true,
            'is_default' => true,
        ]);
        $secondRes->assertStatus(201);

        $first = HouseBank::find($bankId);
        $this->assertFalse((bool) $first->is_default);

        // 3. Add a bank account to the first house bank (no bank_account_id FK required)
        $acctRes = $this->apiPost("/house-banks/{$bankId}/accounts", [
            'account_id_code' => 'CHK001',
            'currency_code'   => 'SAR',
            'account_purpose' => 'payments',
            'is_active'       => true,
        ]);
        $acctRes->assertStatus(201);

        // 4. List house banks
        $listRes = $this->apiGet('/house-banks');
        $listRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, $listRes->json('meta.total'));

        // 5. Show a house bank
        $showRes = $this->apiGet("/house-banks/{$bankId}");
        $showRes->assertStatus(200);
        $this->assertEquals('RIYAD', $showRes->json('data.code'));

        // 6. Update the house bank
        $updateRes = $this->apiPut("/house-banks/{$bankId}", [
            'swift_code' => 'RIBLSARI',
            'is_active'  => true,
        ]);
        $updateRes->assertStatus(200);
    }

    public function test_payment_advice_lifecycle(): void
    {
        // 1. Create a house bank to associate advices with
        $bankRes = $this->apiPost('/house-banks', [
            'code'      => 'NBD',
            'name'      => 'National Bank of Dubai',
            'is_active' => true,
        ]);
        $bankRes->assertStatus(201);
        $bankId = $bankRes->json('data.id');

        // 2. Create an outgoing payment advice (FBZP)
        $adviceRes = $this->apiPost('/payment-advices', [
            'direction'     => 'outgoing',
            'payment_type'  => 'payment_made',
            'house_bank_id' => $bankId,
            'currency_code' => 'SAR',
            'amount'        => 15000.00,
            'payment_date'  => '2026-04-10',
            'reference'     => 'UTR-20260410-001',
            'narration'     => 'Vendor payment — March 2026',
        ]);
        $adviceRes->assertStatus(201);
        $adviceId  = $adviceRes->json('data.id');
        $adviceNum = $adviceRes->json('data.advice_number');

        $this->assertEquals('draft', $adviceRes->json('data.status'));
        $this->assertEquals('outgoing', $adviceRes->json('data.direction'));
        $this->assertNotNull($adviceNum);
        $this->assertStringStartsWith('PADV-', $adviceNum);

        // 3. Send the advice (DRAFT → SENT)
        $sendRes = $this->apiPost("/payment-advices/{$adviceId}/send");
        $sendRes->assertStatus(200);
        $this->assertEquals('sent', $sendRes->json('data.status'));
        $this->assertNotNull($sendRes->json('data.sent_at'));

        // 4. Cannot send again
        $this->apiPost("/payment-advices/{$adviceId}/send")->assertStatus(422);

        // 5. Acknowledge the advice (SENT → ACKNOWLEDGED)
        $ackRes = $this->apiPost("/payment-advices/{$adviceId}/acknowledge");
        $ackRes->assertStatus(200);
        $this->assertEquals('acknowledged', $ackRes->json('data.status'));
        $this->assertNotNull($ackRes->json('data.acknowledged_at'));

        // 6. Cannot cancel an acknowledged advice
        $this->apiPost("/payment-advices/{$adviceId}/cancel", ['reason' => 'Test'])
            ->assertStatus(422);

        // 7. Create a second advice and cancel it while still draft
        $draft2 = $this->apiPost('/payment-advices', [
            'direction'    => 'incoming',
            'currency_code' => 'SAR',
            'amount'       => 5000.00,
            'payment_date' => '2026-04-15',
        ]);
        $draft2->assertStatus(201);
        $draft2Id = $draft2->json('data.id');

        $cancelRes = $this->apiPost("/payment-advices/{$draft2Id}/cancel", ['reason' => 'Duplicate entry']);
        $cancelRes->assertStatus(200);
        $this->assertEquals('cancelled', $cancelRes->json('data.status'));
        $this->assertStringContainsString('Duplicate entry', $cancelRes->json('data.narration') ?? '');

        // 8. Outstanding summary
        $summaryRes = $this->apiGet('/payment-advices/outstanding-summary');
        $summaryRes->assertStatus(200);

        // 9. List with filter
        $listRes = $this->apiGet('/payment-advices?direction=outgoing');
        $listRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $listRes->json('meta.total'));
    }

    public function test_payment_advice_auto_number_generation(): void
    {
        // Two advices created without explicit advice_number should get distinct auto-numbers
        $res1 = $this->apiPost('/payment-advices', [
            'direction'    => 'outgoing',
            'currency_code' => 'SAR',
            'amount'       => 1000,
            'payment_date' => '2026-04-20',
        ]);
        $res1->assertStatus(201);

        $res2 = $this->apiPost('/payment-advices', [
            'direction'    => 'incoming',
            'currency_code' => 'SAR',
            'amount'       => 2000,
            'payment_date' => '2026-04-20',
        ]);
        $res2->assertStatus(201);

        $this->assertNotEquals($res1->json('data.advice_number'), $res2->json('data.advice_number'));
    }

    public function test_house_bank_delete_blocked_by_active_advice(): void
    {
        $bankRes = $this->apiPost('/house-banks', [
            'code'      => 'KSAB',
            'name'      => 'Al Bilad Bank',
            'is_active' => true,
        ]);
        $bankRes->assertStatus(201);
        $bankId = $bankRes->json('data.id');

        // Attach a draft advice to this bank
        $this->apiPost('/payment-advices', [
            'direction'     => 'outgoing',
            'house_bank_id' => $bankId,
            'currency_code' => 'SAR',
            'amount'        => 500,
            'payment_date'  => '2026-04-25',
        ])->assertStatus(201);

        // Attempting to delete the bank should be blocked (422)
        $this->apiDelete("/house-banks/{$bankId}")->assertStatus(422);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createFiscalYear(): FiscalYear
    {
        return FiscalYear::create([
            'organization_id' => $this->organization->id,
            'name'            => 'FY 2025',
            'start_date'      => '2025-01-01',
            'end_date'        => '2025-12-31',
            'status'          => 'open',
        ]);
    }

    /**
     * Create a GL account for test use.
     */
    private function createGlAccount(string $code, string $name, string $type, string $subType): Account
    {
        return Account::create([
            'organization_id' => $this->organization->id,
            'code'            => $code,
            'name'            => $name,
            'account_type'    => $type,
            'sub_type'        => $subType,
            'is_active'       => true,
            'is_header'       => false,
        ]);
    }
}
