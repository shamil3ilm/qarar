<?php

declare(strict_types=1);

namespace Tests\Unit\Orchestrators\Sales;

use App\Events\Sales\InvoicePosted;
use App\Jobs\GenerateInvoiceDocumentJob;
use App\Jobs\RetryComplianceSubmission;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Orchestrators\Sales\PostInvoiceOrchestrator;
use App\Services\Accounting\CreditManagementService;
use App\Services\Accounting\JournalEntryFactory;
use App\Services\Compliance\CompliPayClient;
use App\Services\Core\UserEventService;
use App\Services\Inventory\StockService;
use App\Services\Sales\RebateAccrualService;
use App\Services\Compliance\ComplianceResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

#[\PHPUnit\Framework\Attributes\CoversClass(\App\Orchestrators\Sales\PostInvoiceOrchestrator::class)]
class PostInvoiceOrchestratorTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private MockInterface $journalEntryFactory;
    private MockInterface $stockService;
    private MockInterface $creditManagementService;
    private MockInterface $compliPayClient;
    private MockInterface $userEventService;
    private MockInterface $rebateAccrualService;

    private PostInvoiceOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->setUpOpenFiscalPeriod();
        $this->actingAs($this->user, 'api');

        $this->journalEntryFactory     = Mockery::mock(JournalEntryFactory::class);
        $this->stockService            = Mockery::mock(StockService::class);
        $this->creditManagementService = Mockery::mock(CreditManagementService::class);
        $this->compliPayClient         = Mockery::mock(CompliPayClient::class);
        $this->userEventService        = Mockery::mock(UserEventService::class);
        $this->rebateAccrualService    = Mockery::mock(RebateAccrualService::class);

        $this->orchestrator = new PostInvoiceOrchestrator(
            $this->journalEntryFactory,
            $this->stockService,
            $this->creditManagementService,
            $this->compliPayClient,
            $this->userEventService,
            $this->rebateAccrualService,
        );
    }

    /**
     * Happy path: draft invoice → STATUS_SENT, InvoicePosted fired, PDF job queued.
     */
    public function test_execute_transitions_invoice_to_sent_and_fires_domain_event(): void
    {
        Event::fake([InvoicePosted::class]);
        Queue::fake([GenerateInvoiceDocumentJob::class]);

        $invoice = $this->buildDraftInvoice();
        $journal = $this->fakeJournal();

        $this->journalEntryFactory->allows('forInvoice')->once()->andReturn($journal);
        $this->stockService->allows('recordSale')->andReturn(null);
        $this->userEventService->allows('track')->andReturn(null);
        $this->rebateAccrualService->allows('accrueForInvoice')->andReturn(null);
        $this->compliPayClient->shouldNotReceive('submitInvoice');

        $result = $this->orchestrator->execute($invoice);

        $this->assertEquals(Invoice::STATUS_SENT, $result->status);
        $this->assertEquals($journal->id, $result->journal_entry_id);

        Event::assertDispatched(InvoicePosted::class, function (InvoicePosted $event) use ($invoice) {
            return $event->invoice->id === $invoice->id;
        });

        Queue::assertPushed(GenerateInvoiceDocumentJob::class);
    }

    /**
     * InvoicePosted must be dispatched EVEN when the invoice has no trackable lines.
     * This verifies the latent-bug fix is unconditional.
     */
    public function test_invoice_posted_event_is_always_dispatched_after_transaction(): void
    {
        Event::fake([InvoicePosted::class]);
        Queue::fake();

        $invoice = $this->buildDraftInvoice();
        $journal = $this->fakeJournal();

        $this->journalEntryFactory->allows('forInvoice')->andReturn($journal);
        $this->stockService->allows('recordSale')->andReturn(null);
        $this->userEventService->allows('track')->andReturn(null);
        $this->rebateAccrualService->allows('accrueForInvoice')->andReturn(null);

        $this->orchestrator->execute($invoice);

        Event::assertDispatchedTimes(InvoicePosted::class, 1);
    }

    /**
     * When compliance_status is PENDING, CompliPayClient::submitInvoice() is called
     * after the invoice is transitioned to SENT (which makes requiresCompliance() true).
     */
    public function test_zatca_submission_is_triggered_when_compliance_pending(): void
    {
        Event::fake([InvoicePosted::class]);
        Queue::fake();

        // COMPLIANCE_PENDING + STATUS_SENT → requiresCompliance() returns true
        $invoice = $this->buildDraftInvoice(complianceStatus: Invoice::COMPLIANCE_PENDING);
        $journal = $this->fakeJournal();

        $this->journalEntryFactory->allows('forInvoice')->andReturn($journal);
        $this->stockService->allows('recordSale')->andReturn(null);
        $this->userEventService->allows('track')->andReturn(null);
        $this->rebateAccrualService->allows('accrueForInvoice')->andReturn(null);

        $complianceResult = new ComplianceResult([
            'status' => 'cleared',
            'uuid'   => 'zatca-uuid-123',
            'hash'   => 'hash-abc',
        ]);

        $this->compliPayClient->expects('submitInvoice')->once()->andReturn($complianceResult);

        $this->orchestrator->execute($invoice);
    }

    /**
     * When ZATCA submission fails with a connection error, the invoice is still SENT,
     * a RetryComplianceSubmission job is queued, and the orchestrator does NOT throw.
     */
    public function test_zatca_connection_failure_marks_pending_and_queues_retry(): void
    {
        Event::fake([InvoicePosted::class]);
        Queue::fake();

        $invoice = $this->buildDraftInvoice(complianceStatus: Invoice::COMPLIANCE_PENDING);
        $journal = $this->fakeJournal();

        $this->journalEntryFactory->allows('forInvoice')->andReturn($journal);
        $this->stockService->allows('recordSale')->andReturn(null);
        $this->userEventService->allows('track')->andReturn(null);
        $this->rebateAccrualService->allows('accrueForInvoice')->andReturn(null);

        $this->compliPayClient->allows('submitInvoice')
            ->andThrow(new \Illuminate\Http\Client\ConnectionException('ZATCA unreachable'));

        // Must not throw even when ZATCA is down.
        $result = $this->orchestrator->execute($invoice);

        $this->assertEquals(Invoice::STATUS_SENT, $result->status);
        Queue::assertPushed(RetryComplianceSubmission::class);
    }

    /**
     * Rebate-accrual failure must be absorbed — the invoice still becomes STATUS_SENT.
     */
    public function test_rebate_failure_does_not_roll_back_the_send(): void
    {
        Event::fake([InvoicePosted::class]);
        Queue::fake();

        $invoice = $this->buildDraftInvoice();
        $journal = $this->fakeJournal();

        $this->journalEntryFactory->allows('forInvoice')->andReturn($journal);
        $this->stockService->allows('recordSale')->andReturn(null);
        $this->userEventService->allows('track')->andReturn(null);
        $this->rebateAccrualService->allows('accrueForInvoice')
            ->andThrow(new \RuntimeException('Rebate service timeout'));

        $result = $this->orchestrator->execute($invoice);

        $this->assertEquals(Invoice::STATUS_SENT, $result->status);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function buildDraftInvoice(string $complianceStatus = Invoice::COMPLIANCE_NOT_APPLICABLE): Invoice
    {
        $customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'email'           => null, // suppress customer notification
        ]);

        return Invoice::factory()->create([
            'organization_id'   => $this->organization->id,
            'branch_id'         => $this->branch->id,
            'customer_id'       => $customer->id,
            'status'            => Invoice::STATUS_DRAFT,
            'compliance_status' => $complianceStatus,
            'invoice_type'      => Invoice::TYPE_SIMPLIFIED,
            'currency_code'     => 'SAR',
            'total'             => '1000.00',
        ]);
    }

    /**
     * Return a real persisted JournalEntry so the invoice FK constraint is satisfied.
     * Uses setUpOpenFiscalPeriod() (called in setUp) to ensure a FiscalYear exists.
     */
    private function fakeJournal(): JournalEntry
    {
        $fy = FiscalYear::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->first();

        return JournalEntry::forceCreate([
            'organization_id' => $this->organization->id,
            'fiscal_year_id'  => $fy?->id,
            'entry_number'    => 'JE-TEST-' . uniqid(),
            'entry_date'      => now()->toDateString(),
            'description'     => 'Test journal entry',
            'status'          => JournalEntry::STATUS_POSTED,
            'currency_code'   => 'SAR',
            'exchange_rate'   => 1,
            'total_debit'     => 1000,
            'total_credit'    => 1000,
        ]);
    }
}
