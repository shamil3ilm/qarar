<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\DocumentSplittingRule;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\JournalEntrySplitItem;
use App\Services\Accounting\DocumentSplittingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DocumentSplittingSegmentTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private DocumentSplittingService $service;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->service = app(DocumentSplittingService::class);
        $this->account = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => 'income',
            'sub_type'        => 'sales',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Segment split method (FI-04 / SAP FAGL_SPLIT segment dimension)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_segment_split_creates_split_items_with_segment_id(): void
    {
        $entry = JournalEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'currency_code'   => 'SAR',
        ]);

        // Two base lines carrying segment dimension
        $baseLine1 = JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $this->account->id,
            'segment_id'       => 'EMEA',
            'debit'            => 6000,
            'credit'           => 0,
        ]);

        $baseLine2 = JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $this->account->id,
            'segment_id'       => 'APAC',
            'debit'            => 4000,
            'credit'           => 0,
        ]);

        // One line to be split
        $splitLine = JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $this->account->id,
            'segment_id'       => null,
            'debit'            => 0,
            'credit'           => 10000,
        ]);

        DocumentSplittingRule::factory()->create([
            'organization_id'    => $this->organization->id,
            'split_method'       => 'segment',
            'base_item_category' => 'revenue',
            'is_active'          => true,
            'priority'           => 10,
        ]);

        // Mark the base lines with a category so the rule selects them
        $baseLine1->update(['category' => 'revenue']);
        $baseLine2->update(['category' => 'revenue']);

        $entry->load('lines');
        $this->service->splitDocument($entry);

        $splitItems = JournalEntrySplitItem::where('journal_entry_id', $entry->id)->get();

        $this->assertCount(2, $splitItems);

        $emea = $splitItems->firstWhere('segment_id', 'EMEA');
        $apac = $splitItems->firstWhere('segment_id', 'APAC');

        $this->assertNotNull($emea, 'EMEA split item missing');
        $this->assertNotNull($apac, 'APAC split item missing');

        // EMEA = 60%, APAC = 40%
        $this->assertEquals(6000.0, (float) $emea->credit_amount);
        $this->assertEquals(4000.0, (float) $apac->credit_amount);
    }

    public function test_segment_split_items_have_split_method_recorded(): void
    {
        $entry = JournalEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'currency_code'   => 'SAR',
        ]);

        $baseLine = JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $this->account->id,
            'segment_id'       => 'EMEA',
            'debit'            => 10000,
            'credit'           => 0,
            'category'         => 'revenue',
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $this->account->id,
            'segment_id'       => null,
            'debit'            => 0,
            'credit'           => 10000,
        ]);

        DocumentSplittingRule::factory()->create([
            'organization_id'    => $this->organization->id,
            'split_method'       => 'segment',
            'base_item_category' => 'revenue',
            'is_active'          => true,
        ]);

        $entry->load('lines');
        $this->service->splitDocument($entry);

        $item = JournalEntrySplitItem::where('journal_entry_id', $entry->id)->first();

        $this->assertEquals('segment', $item->split_method);
    }

    public function test_no_split_items_when_base_lines_lack_segment(): void
    {
        $entry = JournalEntry::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $this->account->id,
            'segment_id'       => null,
            'debit'            => 5000,
            'credit'           => 0,
            'category'         => 'revenue',
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $this->account->id,
            'debit'            => 0,
            'credit'           => 5000,
        ]);

        DocumentSplittingRule::factory()->create([
            'organization_id'    => $this->organization->id,
            'split_method'       => 'segment',
            'base_item_category' => 'revenue',
            'is_active'          => true,
        ]);

        $entry->load('lines');
        $this->service->splitDocument($entry);

        $this->assertEquals(0, JournalEntrySplitItem::where('journal_entry_id', $entry->id)->count());
    }

    public function test_preview_split_returns_segment_items(): void
    {
        DocumentSplittingRule::factory()->create([
            'organization_id'    => $this->organization->id,
            'split_method'       => 'segment',
            'base_item_category' => 'revenue',
            'is_active'          => true,
        ]);

        $preview = $this->service->previewSplit([
            'organization_id' => $this->organization->id,
            'currency_code'   => 'SAR',
            'lines'           => [
                ['category' => 'revenue', 'segment_id' => 'EMEA', 'debit' => 7000, 'credit' => 0],
                ['category' => 'revenue', 'segment_id' => 'APAC', 'debit' => 3000, 'credit' => 0],
                ['debit' => 0, 'credit' => 10000],
            ],
        ]);

        $this->assertCount(2, $preview);

        $emea = collect($preview)->firstWhere('segment_id', 'EMEA');
        $this->assertNotNull($emea);
        $this->assertEquals(7000.0, (float) $emea['credit_amount']);
    }
}
