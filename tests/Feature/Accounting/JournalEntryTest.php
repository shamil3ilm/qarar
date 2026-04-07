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

class JournalEntryTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private string $baseUrl = '/journal-entries';
    private FiscalYear $fiscalYear;
    private Account $debitAccount;
    private Account $creditAccount;

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
     * Set up the accounting context needed for journal entries.
     */
    private function setUpAccountingContext(): void
    {
        $this->fiscalYear = FiscalYear::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'name' => 'FY 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => true,
            'is_closed' => false,
        ]);

        $this->debitAccount = Account::withoutGlobalScopes()->create([
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

        $this->creditAccount = Account::withoutGlobalScopes()->create([
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
    }

    /**
     * Create a journal entry with balanced lines.
     */
    private function createJournalEntry(array $overrides = [], string $status = JournalEntry::STATUS_DRAFT): JournalEntry
    {
        $je = JournalEntry::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'entry_date' => '2025-06-15',
            'description' => 'Test journal entry',
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'total_debit' => 1000.0000,
            'total_credit' => 1000.0000,
            'status' => $status,
            'created_by' => $this->user->id,
        ], $overrides));

        JournalEntryLine::create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->debitAccount->id,
            'description' => 'Debit line',
            'debit' => 1000.0000,
            'credit' => 0,
            'line_order' => 1,
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $je->id,
            'account_id' => $this->creditAccount->id,
            'description' => 'Credit line',
            'debit' => 0,
            'credit' => 1000.0000,
            'line_order' => 2,
        ]);

        return $je->fresh();
    }

    /**
     * Build a valid payload for creating a journal entry via API.
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'entry_date' => '2025-06-15',
            'description' => 'API created journal entry',
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'account_id' => $this->debitAccount->id,
                    'debit' => 5000.00,
                    'credit' => 0,
                    'description' => 'Cash received',
                ],
                [
                    'account_id' => $this->creditAccount->id,
                    'debit' => 0,
                    'credit' => 5000.00,
                    'description' => 'Sales revenue',
                ],
            ],
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/journal-entries - List
    // -------------------------------------------------------------------------

    public function test_can_list_journal_entries_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.view']);
        $this->setUpAccountingContext();

        $this->createJournalEntry();
        $this->createJournalEntry(['description' => 'Second entry']);

        $response = $this->apiGet($this->baseUrl);

        $this->assertSuccessResponse($response);
    }

    public function test_list_journal_entries_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/journal-entries', [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_list_journal_entries_requires_permission(): void
    {
        $this->setUpAuthenticatedUser([]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertForbidden($response);
    }

    public function test_list_journal_entries_respects_tenant_isolation(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.view']);
        $this->setUpAccountingContext();

        $ownEntry = $this->createJournalEntry(['description' => 'Own entry']);

        // Create entry in another organization
        $otherOrg = Organization::factory()->create(['country_code' => 'AE', 'base_currency' => 'AED']);
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
        JournalEntry::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'fiscal_year_id' => $otherFy->id,
            'entry_date' => '2025-06-15',
            'description' => 'Other org entry',
            'currency_code' => 'AED',
            'exchange_rate' => 1.0,
            'total_debit' => 500,
            'total_credit' => 500,
            'status' => JournalEntry::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        $descriptions = collect($data)->pluck('description')->toArray();
        $this->assertContains('Own entry', $descriptions);
        $this->assertNotContains('Other org entry', $descriptions);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/journal-entries - Create
    // -------------------------------------------------------------------------

    public function test_can_create_journal_entry_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.create']);
        $this->setUpAccountingContext();

        $response = $this->apiPost($this->baseUrl, $this->validPayload());

        $this->assertCreatedResponse($response);
        $response->assertJsonFragment(['description' => 'API created journal entry']);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $this->organization->id,
            'description' => 'API created journal entry',
            'status' => JournalEntry::STATUS_DRAFT,
        ]);
    }

    public function test_create_journal_entry_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.view']);
        $this->setUpAccountingContext();

        $response = $this->apiPost($this->baseUrl, $this->validPayload());

        $this->assertForbidden($response);
    }

    public function test_create_journal_entry_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/journal-entries', [], [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_create_journal_entry_validates_required_fields(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.create']);
        $this->setUpAccountingContext();

        $response = $this->apiPost($this->baseUrl, []);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_journal_entry_requires_balanced_debits_and_credits(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.create']);
        $this->setUpAccountingContext();

        $response = $this->apiPost($this->baseUrl, [
            'entry_date' => '2025-06-15',
            'description' => 'Unbalanced entry',
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'account_id' => $this->debitAccount->id,
                    'debit' => 5000.00,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->creditAccount->id,
                    'debit' => 0,
                    'credit' => 3000.00,
                ],
            ],
        ]);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_journal_entry_requires_at_least_two_lines(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.create']);
        $this->setUpAccountingContext();

        $response = $this->apiPost($this->baseUrl, [
            'entry_date' => '2025-06-15',
            'description' => 'Single line entry',
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'account_id' => $this->debitAccount->id,
                    'debit' => 5000.00,
                    'credit' => 0,
                ],
            ],
        ]);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_journal_entry_validates_date_within_open_fiscal_year(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.create']);
        $this->setUpAccountingContext();

        // Create an entry with a date outside the fiscal year
        $response = $this->apiPost($this->baseUrl, $this->validPayload([
            'entry_date' => '2020-01-01',
        ]));

        $this->assertErrorResponse($response, 422);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/journal-entries/{je} - Show
    // -------------------------------------------------------------------------

    public function test_can_show_journal_entry_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.view']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry(['description' => 'Detailed entry']);

        $response = $this->apiGet("{$this->baseUrl}/{$je->id}");

        $this->assertSuccessResponse($response);
        $response->assertJsonFragment(['description' => 'Detailed entry']);
    }

    public function test_show_journal_entry_includes_lines(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.view']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry();

        $response = $this->apiGet("{$this->baseUrl}/{$je->id}");

        $this->assertSuccessResponse($response);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'entry_number',
                'entry_date',
                'description',
                'status',
                'lines',
            ],
        ]);
    }

    public function test_show_journal_entry_returns_404_for_other_org(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.view']);
        $this->setUpAccountingContext();

        $otherOrg = Organization::factory()->create(['country_code' => 'AE', 'base_currency' => 'AED']);
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
        $otherJe = JournalEntry::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'fiscal_year_id' => $otherFy->id,
            'entry_date' => '2025-06-15',
            'description' => 'Other org entry',
            'currency_code' => 'AED',
            'exchange_rate' => 1.0,
            'total_debit' => 500,
            'total_credit' => 500,
            'status' => JournalEntry::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$otherJe->id}");

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/journal-entries/{je} - Update Draft
    // -------------------------------------------------------------------------

    public function test_can_update_draft_journal_entry(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.update']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry(['status' => JournalEntry::STATUS_DRAFT]);

        $response = $this->apiPut("{$this->baseUrl}/{$je->id}", [
            'description' => 'Updated description',
        ]);

        $this->assertSuccessResponse($response);
        $response->assertJsonFragment(['description' => 'Updated description']);
    }

    public function test_cannot_update_posted_journal_entry(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.update']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry([], JournalEntry::STATUS_POSTED);
        $je->update(['posted_at' => now(), 'posted_by' => $this->user->id]);

        $response = $this->apiPut("{$this->baseUrl}/{$je->id}", [
            'description' => 'Should not update',
        ]);

        $this->assertErrorResponse($response);
    }

    public function test_update_journal_entry_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.view']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry();

        $response = $this->apiPut("{$this->baseUrl}/{$je->id}", [
            'description' => 'Updated',
        ]);

        $this->assertForbidden($response);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/journal-entries/{je} - Delete Draft
    // -------------------------------------------------------------------------

    public function test_can_delete_draft_journal_entry(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.delete']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry(['status' => JournalEntry::STATUS_DRAFT]);

        $response = $this->apiDelete("{$this->baseUrl}/{$je->id}");

        $this->assertSuccessResponse($response);
    }

    public function test_cannot_delete_posted_journal_entry(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.delete']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry([], JournalEntry::STATUS_POSTED);
        $je->update(['posted_at' => now(), 'posted_by' => $this->user->id]);

        $response = $this->apiDelete("{$this->baseUrl}/{$je->id}");

        $this->assertErrorResponse($response);
    }

    public function test_delete_journal_entry_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.view']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry();

        $response = $this->apiDelete("{$this->baseUrl}/{$je->id}");

        $this->assertForbidden($response);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/journal-entries/{je}/post - Post Entry
    // -------------------------------------------------------------------------

    public function test_can_post_draft_journal_entry(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.post']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry(['status' => JournalEntry::STATUS_DRAFT]);

        $response = $this->apiPost("{$this->baseUrl}/{$je->id}/post");

        $this->assertSuccessResponse($response);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $je->id,
            'status' => JournalEntry::STATUS_POSTED,
        ]);
    }

    public function test_cannot_post_already_posted_entry(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.post']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry([], JournalEntry::STATUS_POSTED);
        $je->update(['posted_at' => now(), 'posted_by' => $this->user->id]);

        $response = $this->apiPost("{$this->baseUrl}/{$je->id}/post");

        $this->assertErrorResponse($response);
    }

    public function test_post_journal_entry_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.view']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry();

        $response = $this->apiPost("{$this->baseUrl}/{$je->id}/post");

        $this->assertForbidden($response);
    }

    public function test_cannot_post_unbalanced_journal_entry(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.post']);
        $this->setUpAccountingContext();

        // Create unbalanced entry directly in DB
        $je = JournalEntry::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'entry_date' => '2025-06-15',
            'description' => 'Unbalanced entry',
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'total_debit' => 5000.0000,
            'total_credit' => 3000.0000,
            'status' => JournalEntry::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$je->id}/post");

        $this->assertErrorResponse($response);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/journal-entries/{je}/void - Void Posted Entry
    // -------------------------------------------------------------------------

    public function test_can_void_posted_journal_entry(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.void']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry([], JournalEntry::STATUS_POSTED);
        $je->update(['posted_at' => now(), 'posted_by' => $this->user->id]);

        $response = $this->apiPost("{$this->baseUrl}/{$je->id}/void", [
            'reason' => 'Entered in error',
        ]);

        $this->assertSuccessResponse($response);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $je->id,
            'status' => JournalEntry::STATUS_VOIDED,
        ]);
    }

    public function test_cannot_void_draft_journal_entry(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.void']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry(['status' => JournalEntry::STATUS_DRAFT]);

        $response = $this->apiPost("{$this->baseUrl}/{$je->id}/void", [
            'reason' => 'Attempted void on draft',
        ]);

        $this->assertErrorResponse($response);
    }

    public function test_void_requires_reason(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.void']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry([], JournalEntry::STATUS_POSTED);
        $je->update(['posted_at' => now(), 'posted_by' => $this->user->id]);

        $response = $this->apiPost("{$this->baseUrl}/{$je->id}/void", []);

        $this->assertErrorResponse($response, 422);
    }

    public function test_void_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.view']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry([], JournalEntry::STATUS_POSTED);

        $response = $this->apiPost("{$this->baseUrl}/{$je->id}/void", [
            'reason' => 'Test',
        ]);

        $this->assertForbidden($response);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/journal-entries/{je}/reverse - Create Reversal
    // -------------------------------------------------------------------------

    public function test_can_reverse_posted_journal_entry(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.reverse']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry([], JournalEntry::STATUS_POSTED);
        $je->update(['posted_at' => now(), 'posted_by' => $this->user->id]);

        $response = $this->apiPost("{$this->baseUrl}/{$je->id}/reverse", [
            'reason' => 'Correction needed',
        ]);

        $this->assertSuccessResponse($response);

        // The original entry should be linked to the reversal
        $je->refresh();
        $this->assertNotNull($je->reversed_by_id);
    }

    public function test_cannot_reverse_draft_journal_entry(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.reverse']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry(['status' => JournalEntry::STATUS_DRAFT]);

        $response = $this->apiPost("{$this->baseUrl}/{$je->id}/reverse", [
            'reason' => 'Attempted reversal on draft',
        ]);

        $this->assertErrorResponse($response);
    }

    public function test_cannot_reverse_already_reversed_journal_entry(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.reverse']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry([], JournalEntry::STATUS_POSTED);
        $je->update(['posted_at' => now(), 'posted_by' => $this->user->id]);

        // Reverse the first time
        $this->apiPost("{$this->baseUrl}/{$je->id}/reverse", [
            'reason' => 'First reversal',
        ]);

        // Attempt a second reversal
        $response = $this->apiPost("{$this->baseUrl}/{$je->id}/reverse", [
            'reason' => 'Second reversal attempt',
        ]);

        $this->assertErrorResponse($response);
    }

    public function test_reverse_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.view']);
        $this->setUpAccountingContext();

        $je = $this->createJournalEntry([], JournalEntry::STATUS_POSTED);

        $response = $this->apiPost("{$this->baseUrl}/{$je->id}/reverse", [
            'reason' => 'Test',
        ]);

        $this->assertForbidden($response);
    }

    // -------------------------------------------------------------------------
    // Business Logic Tests
    // -------------------------------------------------------------------------

    public function test_journal_entry_number_is_auto_generated(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.create']);
        $this->setUpAccountingContext();

        $response = $this->apiPost($this->baseUrl, $this->validPayload());

        $this->assertCreatedResponse($response);
        $entryNumber = $response->json('data.entry_number');
        $this->assertNotNull($entryNumber);
        $this->assertStringStartsWith('JE-', $entryNumber);
    }

    public function test_cannot_post_entry_in_closed_fiscal_year(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.post']);
        $this->setUpAccountingContext();

        // Close the fiscal year
        $this->fiscalYear->update(['is_closed' => true, 'closed_at' => now()]);

        $je = $this->createJournalEntry(['status' => JournalEntry::STATUS_DRAFT]);

        $response = $this->apiPost("{$this->baseUrl}/{$je->id}/post");

        $this->assertErrorResponse($response);
    }

    public function test_created_entry_starts_as_draft(): void
    {
        $this->setUpAuthenticatedUser(['accounting.journals.create']);
        $this->setUpAccountingContext();

        $response = $this->apiPost($this->baseUrl, $this->validPayload());

        $this->assertCreatedResponse($response);
        $response->assertJsonFragment(['status' => JournalEntry::STATUS_DRAFT]);
    }
}
