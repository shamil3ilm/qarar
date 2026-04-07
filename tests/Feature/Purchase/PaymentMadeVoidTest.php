<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Purchase\PaymentMade;
use App\Models\Sales\Contact;
use App\Services\Purchase\PaymentMadeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Unit-level tests for PaymentMadeService::void().
 *
 * Covers two regression scenarios:
 *   HIGH-5 — void() with a null journal_entry_id must not throw and must
 *             still transition the payment to STATUS_VOIDED.
 *   HIGH-3 (guard) — void() with a real, posted journal entry must mark that
 *             entry as voided.
 */
class PaymentMadeVoidTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private PaymentMadeService $service;
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.payments.view',
            'purchase.payments.create',
            'purchase.payments.void',
        ]);
        $this->setUpOpenFiscalPeriod();

        $this->supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_SUPPLIER,
            'currency_code'   => 'SAR',
        ]);

        $this->service = app(PaymentMadeService::class);
    }

    // -------------------------------------------------------------------------
    // HIGH-5 regression: void() with null journal_entry_id must not throw
    // -------------------------------------------------------------------------

    public function test_void_with_null_journal_entry_does_not_throw(): void
    {
        // Arrange: completed payment with no journal entry (e.g. created before
        // GL accounts were configured, so journal creation was skipped).
        $payment = PaymentMade::factory()->completed()->create([
            'organization_id'  => $this->organization->id,
            'branch_id'        => $this->branch->id,
            'supplier_id'      => $this->supplier->id,
            'journal_entry_id' => null,
            'amount'           => 1000.00,
            'currency_code'    => 'SAR',
            'created_by'       => $this->user->id,
        ]);

        $this->assertNull($payment->journal_entry_id, 'Pre-condition: payment must have no journal entry');

        // Act + Assert: no exception must be thrown
        $voided = $this->service->void($payment, 'Test void — no journal entry');

        // Payment must transition to VOIDED
        $this->assertEquals(
            PaymentMade::STATUS_VOIDED,
            $voided->status,
            'Payment must be STATUS_VOIDED after void() even when journal_entry_id is null'
        );

        // Persisted state must also be VOIDED
        $this->assertEquals(
            PaymentMade::STATUS_VOIDED,
            $payment->fresh()->status,
            'Persisted payment status must be STATUS_VOIDED'
        );
    }

    public function test_void_with_null_journal_entry_does_not_leave_payment_in_completed_state(): void
    {
        // Secondary assertion for the same HIGH-5 scenario: confirms the status
        // column is written correctly rather than the service silently failing.
        $payment = PaymentMade::factory()->completed()->create([
            'organization_id'  => $this->organization->id,
            'branch_id'        => $this->branch->id,
            'supplier_id'      => $this->supplier->id,
            'journal_entry_id' => null,
            'amount'           => 500.00,
            'currency_code'    => 'SAR',
            'created_by'       => $this->user->id,
        ]);

        $this->service->void($payment, 'Idempotency check');

        $fresh = $payment->fresh();
        $this->assertNotEquals(
            PaymentMade::STATUS_COMPLETED,
            $fresh->status,
            'After void() the payment must not remain in STATUS_COMPLETED'
        );
    }

    // -------------------------------------------------------------------------
    // void() with a real journal entry — entry must be voided too
    // -------------------------------------------------------------------------

    public function test_void_with_journal_entry_voids_the_journal_entry(): void
    {
        // Arrange: create a posted journal entry and link it to the payment.
        $fiscalYear = FiscalYear::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->firstOrFail();

        $journalEntry = JournalEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'fiscal_year_id'  => $fiscalYear->id,
            'status'          => JournalEntry::STATUS_POSTED,
            'currency_code'   => 'SAR',
            'total_debit'     => 2000.00,
            'total_credit'    => 2000.00,
        ]);

        $payment = PaymentMade::factory()->completed()->create([
            'organization_id'  => $this->organization->id,
            'branch_id'        => $this->branch->id,
            'supplier_id'      => $this->supplier->id,
            'journal_entry_id' => $journalEntry->id,
            'amount'           => 2000.00,
            'currency_code'    => 'SAR',
            'created_by'       => $this->user->id,
        ]);

        $this->assertEquals(
            JournalEntry::STATUS_POSTED,
            $journalEntry->fresh()->status,
            'Pre-condition: journal entry must be POSTED before void'
        );

        // Act
        $voided = $this->service->void($payment, 'Void with journal entry test');

        // Payment must be VOIDED
        $this->assertEquals(
            PaymentMade::STATUS_VOIDED,
            $voided->status,
            'Payment must be STATUS_VOIDED after void()'
        );

        // Journal entry must also be VOIDED
        $this->assertEquals(
            JournalEntry::STATUS_VOIDED,
            $journalEntry->fresh()->status,
            'Journal entry must be STATUS_VOIDED after the payment is voided'
        );
    }

    public function test_void_of_already_voided_payment_throws_exception(): void
    {
        // Guard: calling void() on an already-voided payment must throw.
        $payment = PaymentMade::factory()->voided()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'supplier_id'     => $this->supplier->id,
            'amount'          => 300.00,
            'currency_code'   => 'SAR',
            'created_by'      => $this->user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->void($payment, 'Double-void attempt');
    }
}
