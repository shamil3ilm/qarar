<?php

declare(strict_types=1);

namespace Tests\Feature\CriticalFlows;

use App\Events\Sales\InvoicePosted;
use App\Models\Accounting\Account;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Validates the PostInvoiceOrchestrator cross-module flow:
 * draft → send (fires InvoicePosted) → void / credit-note
 */
class InvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;
    private Account $incomeAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.invoices.view',
            'sales.invoices.create',
            'sales.invoices.edit',
            'sales.invoices.send',
            'sales.invoices.void',
            'sales.invoices.credit-note',
            'sales.contacts.view',
            'sales.contacts.create',
        ]);
        $this->setUpOpenFiscalPeriod();

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_CUSTOMER,
            'currency_code'   => 'SAR',
        ]);

        $this->incomeAccount = $this->setUpInvoiceAccounts();
    }

    // ── Happy-path lifecycle ─────────────────────────────────────────────────

    public function test_sending_draft_invoice_transitions_status_to_sent(): void
    {
        Queue::fake();

        [$invoice] = $this->buildDraftInvoice();

        $response = $this->apiPost("/sales/invoices/{$invoice->id}/send");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', Invoice::STATUS_SENT);
    }

    public function test_sending_invoice_dispatches_invoice_posted_event(): void
    {
        Queue::fake();
        // Only fake InvoicePosted — bare Event::fake() intercepts Eloquent creating hooks
        Event::fake([InvoicePosted::class]);

        [$invoice] = $this->buildDraftInvoice();

        $this->apiPost("/sales/invoices/{$invoice->id}/send");

        Event::assertDispatched(InvoicePosted::class, function (InvoicePosted $event) use ($invoice): bool {
            return $event->invoice->id === $invoice->id;
        });
    }

    public function test_sent_invoice_can_be_voided(): void
    {
        Queue::fake();

        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'status'          => Invoice::STATUS_SENT,
            'total'           => 500.00,
            'amount_due'      => 500.00,
        ]);

        $response = $this->apiPost("/sales/invoices/{$invoice->id}/void", [
            'reason' => 'Test void reason',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', Invoice::STATUS_VOIDED);
    }

    public function test_sent_invoice_can_generate_credit_note(): void
    {
        Queue::fake();

        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'status'          => Invoice::STATUS_SENT,
            'total'           => 1150.00,
            'amount_due'      => 1150.00,
            'tax_amount'      => 150.00,
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'account_id' => $this->incomeAccount->id,
            'quantity'   => 1,
            'unit_price' => 1000.00,
            'subtotal'   => 1000.00,
            'total'      => 1150.00,
            'tax_rate'   => 15,
            'tax_amount' => 150.00,
        ]);

        $response = $this->apiPost("/sales/invoices/{$invoice->id}/credit-note", [
            'reason' => 'Customer return',
            'lines'  => [
                [
                    'description' => 'Returned item',
                    'quantity'    => 1,
                    'unit_price'  => 1000.00,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.invoice_type', Invoice::TYPE_CREDIT_NOTE);
    }

    // ── State-machine guards ─────────────────────────────────────────────────

    public function test_cannot_send_already_sent_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'status'          => Invoice::STATUS_SENT,
        ]);

        $response = $this->apiPost("/sales/invoices/{$invoice->id}/send");

        $response->assertStatus(422);
    }

    public function test_draft_invoice_can_also_be_voided(): void
    {
        // State machine: DRAFT → VOIDED is a valid transition (allows discarding before sending)
        [$invoice] = $this->buildDraftInvoice();

        $response = $this->apiPost("/sales/invoices/{$invoice->id}/void", [
            'reason' => 'Discarding unwanted draft',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.status', Invoice::STATUS_VOIDED);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function setUpInvoiceAccounts(): Account
    {
        $receivable = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => Account::SUBTYPE_RECEIVABLE,
            'name'            => 'Accounts Receivable',
            'code'            => '1100',
            'currency_code'   => null,
        ]);

        $this->customer->update(['receivable_account_id' => $receivable->id]);

        return Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_INCOME,
            'sub_type'        => Account::SUBTYPE_SALES,
            'name'            => 'Sales Revenue',
            'code'            => '4000',
            'currency_code'   => null,
        ]);
    }

    /** @return array{Invoice, InvoiceLine} */
    private function buildDraftInvoice(): array
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'status'          => Invoice::STATUS_DRAFT,
            'total'           => 1000.00,
            'amount_due'      => 1000.00,
            'tax_amount'      => 0,
        ]);

        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'account_id' => $this->incomeAccount->id,
            'quantity'   => 1,
            'unit_price' => 1000.00,
            'subtotal'   => 1000.00,
            'total'      => 1000.00,
        ]);

        return [$invoice, $line];
    }
}
