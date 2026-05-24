<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\FinancialStatementVersion;
use App\Models\Accounting\FinancialStatementVersionNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class FinancialStatementVersionTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private string $baseUrl = '/api/v1/financial-statement-versions';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.fsv.view',
            'accounting.fsv.create',
            'accounting.fsv.edit',
            'accounting.fsv.delete',
        ]);
    }

    // -------------------------------------------------------------------------
    // CRUD — index
    // -------------------------------------------------------------------------

    public function test_can_list_financial_statement_versions(): void
    {
        FinancialStatementVersion::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_list_filters_by_type(): void
    {
        FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => FinancialStatementVersion::TYPE_BALANCE_SHEET,
        ]);
        FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => FinancialStatementVersion::TYPE_INCOME_STATEMENT,
        ]);

        $response = $this->withToken($this->token)
            ->getJson($this->baseUrl . '?type=balance_sheet');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('balance_sheet', $data[0]['type']);
    }

    public function test_list_filters_by_is_active(): void
    {
        FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson($this->baseUrl . '?is_active=1');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertTrue($data[0]['is_active']);
    }

    // -------------------------------------------------------------------------
    // CRUD — store
    // -------------------------------------------------------------------------

    public function test_can_create_financial_statement_version(): void
    {
        $response = $this->withToken($this->token)
            ->postJson($this->baseUrl, [
                'name' => 'My Balance Sheet',
                'type' => FinancialStatementVersion::TYPE_BALANCE_SHEET,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'My Balance Sheet')
            ->assertJsonPath('data.type', 'balance_sheet');

        $this->assertDatabaseHas('financial_statement_versions', [
            'name' => 'My Balance Sheet',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_create_requires_name_and_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson($this->baseUrl, []);

        $response->assertStatus(422);
    }

    public function test_create_rejects_invalid_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson($this->baseUrl, [
                'name' => 'Bad Type',
                'type' => 'trial_balance', // not a valid enum value
            ]);

        $response->assertStatus(422);
    }

    public function test_creating_default_fsv_demotes_previous_default(): void
    {
        $first = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => FinancialStatementVersion::TYPE_BALANCE_SHEET,
            'is_default' => true,
        ]);

        $response = $this->withToken($this->token)
            ->postJson($this->baseUrl, [
                'name' => 'New Default BS',
                'type' => FinancialStatementVersion::TYPE_BALANCE_SHEET,
                'is_default' => true,
            ]);

        $response->assertStatus(201);

        // First FSV must no longer be default
        $this->assertDatabaseHas('financial_statement_versions', [
            'id' => $first->id,
            'is_default' => false,
        ]);

        // New FSV must be default
        $this->assertDatabaseHas('financial_statement_versions', [
            'name' => 'New Default BS',
            'is_default' => true,
        ]);
    }

    public function test_setting_default_does_not_affect_other_types(): void
    {
        $incomeDefault = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => FinancialStatementVersion::TYPE_INCOME_STATEMENT,
            'is_default' => true,
        ]);

        $this->withToken($this->token)
            ->postJson($this->baseUrl, [
                'name' => 'New Default BS',
                'type' => FinancialStatementVersion::TYPE_BALANCE_SHEET,
                'is_default' => true,
            ])
            ->assertStatus(201);

        // Income statement default must be untouched
        $this->assertDatabaseHas('financial_statement_versions', [
            'id' => $incomeDefault->id,
            'is_default' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // CRUD — show
    // -------------------------------------------------------------------------

    public function test_can_show_fsv_with_node_tree(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $parent = FinancialStatementVersionNode::factory()->create([
            'organization_id' => $this->organization->id,
            'fsv_id' => $fsv->id,
            'node_type' => 'header',
            'label' => 'Assets',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        FinancialStatementVersionNode::factory()->create([
            'organization_id' => $this->organization->id,
            'fsv_id' => $fsv->id,
            'node_type' => 'total',
            'label' => 'Total Assets',
            'parent_id' => $parent->id,
            'sort_order' => 99,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("{$this->baseUrl}/{$fsv->uuid}");

        // The nodes relation loads all FSV nodes (parent + children flat list)
        $response->assertStatus(200)
            ->assertJsonPath('data.uuid', $fsv->uuid)
            ->assertJsonCount(2, 'data.nodes');
    }

    // -------------------------------------------------------------------------
    // CRUD — update
    // -------------------------------------------------------------------------

    public function test_can_update_fsv_name_and_description(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Old Name',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("{$this->baseUrl}/{$fsv->uuid}", [
                'name' => 'New Name',
                'description' => 'Updated description.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('financial_statement_versions', [
            'id' => $fsv->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_setting_default_demotes_previous_default(): void
    {
        $old = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => FinancialStatementVersion::TYPE_CASH_FLOW,
            'is_default' => true,
        ]);

        $new = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => FinancialStatementVersion::TYPE_CASH_FLOW,
            'is_default' => false,
        ]);

        $this->withToken($this->token)
            ->putJson("{$this->baseUrl}/{$new->uuid}", ['is_default' => true])
            ->assertStatus(200);

        $this->assertDatabaseHas('financial_statement_versions', ['id' => $old->id, 'is_default' => false]);
        $this->assertDatabaseHas('financial_statement_versions', ['id' => $new->id, 'is_default' => true]);
    }

    // -------------------------------------------------------------------------
    // CRUD — delete
    // -------------------------------------------------------------------------

    public function test_can_soft_delete_fsv(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("{$this->baseUrl}/{$fsv->uuid}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('financial_statement_versions', ['id' => $fsv->id]);
    }

    // -------------------------------------------------------------------------
    // Node management — addNode
    // -------------------------------------------------------------------------

    public function test_can_add_header_node_to_fsv(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("{$this->baseUrl}/{$fsv->uuid}/nodes", [
                'node_type' => 'header',
                'label' => 'Current Assets',
                'sort_order' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.label', 'Current Assets')
            ->assertJsonPath('data.node_type', 'header');

        $this->assertDatabaseHas('financial_statement_version_nodes', [
            'fsv_id' => $fsv->id,
            'node_type' => 'header',
            'label' => 'Current Assets',
        ]);
    }

    public function test_can_add_account_node_linked_to_chart_of_accounts(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $account = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => Account::SUBTYPE_CASH,
            'currency_code' => 'SAR',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("{$this->baseUrl}/{$fsv->uuid}/nodes", [
                'node_type' => 'account',
                'label' => $account->name,
                'account_id' => $account->id,
                'sign' => 1,
                'sort_order' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.node_type', 'account')
            ->assertJsonPath('data.account_id', $account->id);
    }

    public function test_account_node_requires_account_id(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("{$this->baseUrl}/{$fsv->uuid}/nodes", [
                'node_type' => 'account',
                'label' => 'Cash',
                // account_id missing
            ]);

        $response->assertStatus(422);
    }

    public function test_add_node_rejects_invalid_node_type(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("{$this->baseUrl}/{$fsv->uuid}/nodes", [
                'node_type' => 'subtotal', // invalid
                'label' => 'Something',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_add_child_node_under_parent(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $parent = FinancialStatementVersionNode::factory()->create([
            'organization_id' => $this->organization->id,
            'fsv_id' => $fsv->id,
            'node_type' => 'header',
            'label' => 'Assets',
            'parent_id' => null,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("{$this->baseUrl}/{$fsv->uuid}/nodes", [
                'node_type' => 'total',
                'label' => 'Total Assets',
                'parent_id' => $parent->id,
                'sort_order' => 99,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('financial_statement_version_nodes', [
            'fsv_id' => $fsv->id,
            'parent_id' => $parent->id,
            'label' => 'Total Assets',
        ]);
    }

    // -------------------------------------------------------------------------
    // Node management — removeNode
    // -------------------------------------------------------------------------

    public function test_can_remove_a_node(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $node = FinancialStatementVersionNode::factory()->create([
            'organization_id' => $this->organization->id,
            'fsv_id' => $fsv->id,
            'node_type' => 'header',
            'label' => 'To Remove',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("{$this->baseUrl}/{$fsv->uuid}/nodes/{$node->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('financial_statement_version_nodes', ['id' => $node->id]);
    }

    public function test_removing_parent_node_reparents_children_to_grandparent(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $grandparent = FinancialStatementVersionNode::factory()->create([
            'organization_id' => $this->organization->id,
            'fsv_id' => $fsv->id,
            'node_type' => 'header',
            'label' => 'Grandparent',
            'parent_id' => null,
        ]);

        $parent = FinancialStatementVersionNode::factory()->create([
            'organization_id' => $this->organization->id,
            'fsv_id' => $fsv->id,
            'node_type' => 'header',
            'label' => 'Parent',
            'parent_id' => $grandparent->id,
        ]);

        $child = FinancialStatementVersionNode::factory()->create([
            'organization_id' => $this->organization->id,
            'fsv_id' => $fsv->id,
            'node_type' => 'total',
            'label' => 'Child',
            'parent_id' => $parent->id,
        ]);

        $this->withToken($this->token)
            ->deleteJson("{$this->baseUrl}/{$fsv->uuid}/nodes/{$parent->id}")
            ->assertStatus(200);

        // Child should now point to grandparent
        $this->assertDatabaseHas('financial_statement_version_nodes', [
            'id' => $child->id,
            'parent_id' => $grandparent->id,
        ]);
    }

    public function test_remove_node_returns_404_when_node_belongs_to_different_fsv(): void
    {
        $fsvA = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $fsvB = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $nodeOnB = FinancialStatementVersionNode::factory()->create([
            'organization_id' => $this->organization->id,
            'fsv_id' => $fsvB->id,
            'node_type' => 'header',
            'label' => 'B Node',
        ]);

        // Try to delete a node of FSV-B through FSV-A's URL
        $this->withToken($this->token)
            ->deleteJson("{$this->baseUrl}/{$fsvA->uuid}/nodes/{$nodeOnB->id}")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Generate — structure output
    // -------------------------------------------------------------------------

    public function test_generate_returns_fsv_metadata_and_node_tree(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Balance Sheet Q1',
            'type' => FinancialStatementVersion::TYPE_BALANCE_SHEET,
        ]);

        FinancialStatementVersionNode::factory()->create([
            'organization_id' => $this->organization->id,
            'fsv_id' => $fsv->id,
            'node_type' => 'header',
            'label' => 'Assets',
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("{$this->baseUrl}/{$fsv->uuid}/generate?period_start=2026-01-01&period_end=2026-03-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.fsv_id', $fsv->id)
            ->assertJsonPath('data.fsv_name', 'Balance Sheet Q1')
            ->assertJsonPath('data.type', 'balance_sheet')
            ->assertJsonPath('data.period_start', '2026-01-01')
            ->assertJsonPath('data.period_end', '2026-03-31')
            ->assertJsonCount(1, 'data.nodes');
    }

    public function test_generate_requires_period_start_and_end(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->withToken($this->token)
            ->getJson("{$this->baseUrl}/{$fsv->uuid}/generate")
            ->assertStatus(422);
    }

    public function test_generate_rejects_period_end_before_start(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->withToken($this->token)
            ->getJson("{$this->baseUrl}/{$fsv->uuid}/generate?period_start=2026-03-31&period_end=2026-01-01")
            ->assertStatus(422);
    }

    public function test_generate_account_node_carries_sign(): void
    {
        $fsv = FinancialStatementVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => FinancialStatementVersion::TYPE_INCOME_STATEMENT,
        ]);

        $account = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => Account::TYPE_INCOME,
            'sub_type' => Account::SUBTYPE_SALES,
            'currency_code' => 'SAR',
        ]);

        FinancialStatementVersionNode::factory()->create([
            'organization_id' => $this->organization->id,
            'fsv_id' => $fsv->id,
            'node_type' => 'account',
            'label' => 'Revenue',
            'account_id' => $account->id,
            'sign' => -1, // Revenue accounts are credited; sign flips to positive P&L
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("{$this->baseUrl}/{$fsv->uuid}/generate?period_start=2026-01-01&period_end=2026-03-31");

        $response->assertStatus(200);
        // No journal entries → balance is 0, sign doesn't change anything, but sign field
        // must be present in the node payload
        $firstNode = $response->json('data.nodes.0');
        $this->assertArrayHasKey('sign', $firstNode);
        $this->assertSame(-1, $firstNode['sign']);
    }

    // -------------------------------------------------------------------------
    // Unauthenticated requests
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson($this->baseUrl)->assertStatus(401);
    }
}
