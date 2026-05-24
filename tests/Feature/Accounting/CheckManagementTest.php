<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\BankAccount;
use App\Models\Accounting\CheckBook;
use App\Models\Accounting\CheckRegisterEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CheckManagementTest extends TestCase
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

    private function makeBankAccount(): BankAccount
    {
        $account = \App\Models\Accounting\Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => 'asset',
            'sub_type'        => 'bank',
        ]);

        return BankAccount::factory()->create([
            'organization_id' => $this->organization->id,
            'gl_account_id'   => $account->id,
            'account_type'    => 'current',
        ]);
    }

    private function makeCheckBook(array $overrides = []): CheckBook
    {
        $bankAccount = $this->makeBankAccount();

        return CheckBook::create(array_merge([
            'organization_id'     => $this->organization->id,
            'bank_account_id'     => $bankAccount->id,
            'check_book_number'   => 'CB-' . fake()->unique()->numerify('####'),
            'from_check_number'   => '1001',
            'to_check_number'     => '1100',
            'current_check_number' => '1001',
            'status'              => 'active',
        ], $overrides));
    }

    private function makeCheck(array $overrides = []): CheckRegisterEntry
    {
        return CheckRegisterEntry::create(array_merge([
            'organization_id' => $this->organization->id,
            'check_number'    => 'CHK-' . fake()->unique()->numerify('####'),
            'check_type'      => 'payment',
            'direction'       => 'issued',
            'check_date'      => now()->toDateString(),
            'amount'          => 5000.00,
            'currency_code'   => 'SAR',
            'status'          => 'draft',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Check Books — index
    // -------------------------------------------------------------------------

    public function test_list_books_returns_paginated_list(): void
    {
        $this->makeCheckBook();
        $this->makeCheckBook();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/check-books');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_list_books_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/check-books');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Check Books — create
    // -------------------------------------------------------------------------

    public function test_create_book_stores_check_book(): void
    {
        $bankAccount = $this->makeBankAccount();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/check-books', [
                'bank_account_id'   => $bankAccount->id,
                'check_book_number' => 'CB-9001',
                'from_check_number' => '2001',
                'to_check_number'   => '2100',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.check_book_number', 'CB-9001');
    }

    public function test_create_book_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/check-books', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Check Books — update
    // -------------------------------------------------------------------------

    public function test_update_book_modifies_check_book(): void
    {
        $book = $this->makeCheckBook();

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/check-books/' . $book->id, [
                'status' => 'exhausted',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'exhausted');
    }

    // -------------------------------------------------------------------------
    // Check Books — destroy
    // -------------------------------------------------------------------------

    public function test_destroy_book_soft_deletes(): void
    {
        $book = $this->makeCheckBook();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/check-books/' . $book->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('check_books', ['id' => $book->id]);
    }

    // -------------------------------------------------------------------------
    // Checks — index
    // -------------------------------------------------------------------------

    public function test_list_checks_returns_paginated_list(): void
    {
        $this->makeCheck();
        $this->makeCheck();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/checks');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Checks — create
    // -------------------------------------------------------------------------

    public function test_create_check_stores_check(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/checks', [
                'check_number' => 'CHK-5001',
                'check_type'   => 'payment',
                'direction'    => 'issued',
                'check_date'   => now()->toDateString(),
                'amount'       => 1500.00,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.check_number', 'CHK-5001');
    }

    public function test_create_check_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/checks', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Checks — show
    // -------------------------------------------------------------------------

    public function test_show_check_returns_details(): void
    {
        $check = $this->makeCheck();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/checks/' . $check->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $check->id);
    }

    // -------------------------------------------------------------------------
    // Checks — outstanding
    // -------------------------------------------------------------------------

    public function test_outstanding_returns_checks(): void
    {
        $this->makeCheck(['status' => 'issued']);
        $this->makeCheck(['status' => 'cleared']); // not outstanding

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/checks/outstanding');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Checks — state transitions
    // -------------------------------------------------------------------------

    public function test_print_check_marks_as_printed(): void
    {
        $check = $this->makeCheck(['status' => 'draft']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/checks/' . $check->id . '/print');

        $response->assertStatus(200);
        $this->assertEquals('printed', $check->fresh()->status);
    }

    public function test_issue_check_marks_as_issued(): void
    {
        $check = $this->makeCheck(['status' => 'printed']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/checks/' . $check->id . '/issue');

        $response->assertStatus(200);
        $this->assertEquals('issued', $check->fresh()->status);
    }

    public function test_clear_check_marks_as_cleared(): void
    {
        $check = $this->makeCheck(['status' => 'issued']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/checks/' . $check->id . '/clear');

        $response->assertStatus(200);
        $this->assertEquals('cleared', $check->fresh()->status);
    }

    public function test_bounce_check_requires_reason(): void
    {
        $check = $this->makeCheck(['status' => 'issued']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/checks/' . $check->id . '/bounce', []);

        $response->assertStatus(422);
    }

    public function test_cancel_check_soft_deletes(): void
    {
        $check = $this->makeCheck(['status' => 'draft']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/checks/' . $check->id . '/cancel');

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $check->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/check-books')->assertStatus(401);
        $this->getJson('/api/v1/checks')->assertStatus(401);
    }
}
