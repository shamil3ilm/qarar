<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\PaymentTerm;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CashDiscountTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTerm(array $overrides = []): PaymentTerm
    {
        return PaymentTerm::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'PT-' . fake()->unique()->numerify('##'),
            'name'            => '2/10 Net 30',
            'net_days'        => 30,
            'discount_days'   => 10,
            'discount_pct'    => 2.00,
            'is_active'       => true,
        ], $overrides));
    }

    private function makeInvoice(array $overrides = []): Invoice
    {
        $contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        return Invoice::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id'     => $contact->id,
            'status'          => Invoice::STATUS_SENT,
            'invoice_date'    => now()->subDays(5)->toDateString(),
            'due_date'        => now()->addDays(25)->toDateString(),
            'total'           => 10000.00,
            'amount_paid'     => 0,
            'amount_due'      => 10000.00,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Payment Terms — index
    // -------------------------------------------------------------------------

    public function test_index_terms_returns_active_terms(): void
    {
        $this->makeTerm(['code' => 'T1']);
        $this->makeTerm(['code' => 'T2', 'is_active' => false]); // inactive — excluded

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-terms');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_terms_returns_empty_for_new_org(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/payment-terms');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Payment Terms — store
    // -------------------------------------------------------------------------

    public function test_store_term_creates_payment_term(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-terms', [
                'code'          => 'NET30',
                'name'          => 'Net 30 Days',
                'net_days'      => 30,
                'discount_days' => 10,
                'discount_pct'  => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'NET30');
    }

    public function test_store_term_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-terms', []);

        $response->assertStatus(422);
    }

    public function test_store_term_rejects_invalid_discount_pct(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/payment-terms', [
                'code'          => 'BAD',
                'name'          => 'Bad',
                'net_days'      => 30,
                'discount_days' => 10,
                'discount_pct'  => 150, // > 100
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Preview
    // -------------------------------------------------------------------------

    public function test_preview_returns_eligible_discount(): void
    {
        $term    = $this->makeTerm(['discount_days' => 10, 'discount_pct' => 2.00]);
        $invoice = $this->makeInvoice(['invoice_date' => now()->subDays(3)->toDateString()]); // 3 days ago → within 10-day window

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cash-discounts/preview', [
                'invoice_id'      => $invoice->id,
                'payment_term_id' => $term->id,
                'payment_date'    => now()->toDateString(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.eligible', true);

        // 2% of 10000 = 200
        $this->assertEquals('200.0000', $response->json('data.discount_amount'));
    }

    public function test_preview_returns_ineligible_when_outside_discount_window(): void
    {
        $term    = $this->makeTerm(['discount_days' => 5, 'discount_pct' => 2.00]);
        $invoice = $this->makeInvoice(['invoice_date' => now()->subDays(10)->toDateString()]); // 10 days ago → outside 5-day window

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cash-discounts/preview', [
                'invoice_id'      => $invoice->id,
                'payment_term_id' => $term->id,
                'payment_date'    => now()->toDateString(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.discount_amount', '0.0000');
    }

    public function test_preview_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cash-discounts/preview', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Apply
    // -------------------------------------------------------------------------

    public function test_apply_posts_discount_and_updates_invoice(): void
    {
        $term    = $this->makeTerm(['discount_days' => 10, 'discount_pct' => 2.00]);
        $invoice = $this->makeInvoice(['invoice_date' => now()->subDays(3)->toDateString()]);

        $account = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => 'expense',
            'sub_type'        => 'operating_expense',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cash-discounts/apply', [
                'invoice_id'             => $invoice->id,
                'payment_term_id'        => $term->id,
                'payment_date'           => now()->toDateString(),
                'discount_gl_account_id' => $account->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.eligible', true);

        $this->assertEquals('200.0000', $response->json('data.discount_amount'));

        // Invoice amount_paid should increase by discount amount
        $invoice->refresh();
        $this->assertEquals('200.0000', $invoice->amount_paid);
    }

    public function test_apply_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/cash-discounts/apply', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/payment-terms')->assertStatus(401);
        $this->postJson('/api/v1/cash-discounts/preview')->assertStatus(401);
    }
}
