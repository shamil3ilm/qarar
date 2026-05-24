<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\DunningBlock;
use App\Models\Accounting\DunningLevel;
use App\Models\Accounting\DunningRun;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DunningTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.dunning.view',
            'accounting.dunning.configure',
            'accounting.dunning.run',
            'accounting.dunning.send',
            'accounting.dunning.block',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeLevel(array $overrides = []): DunningLevel
    {
        return DunningLevel::create(array_merge([
            'organization_id' => $this->organization->id,
            'level_number'    => fake()->unique()->numberBetween(1, 9),
            'name'            => 'Level ' . fake()->numerify('##'),
            'days_overdue_from' => 30,
            'is_active'       => true,
        ], $overrides));
    }

    private function makeRun(array $overrides = []): DunningRun
    {
        return DunningRun::create(array_merge([
            'organization_id' => $this->organization->id,
            'run_date'        => '2025-01-31',
            'status'          => 'draft',
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    private function makeContact(): Contact
    {
        return Contact::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    private function makeBlock(Contact $contact, array $overrides = []): DunningBlock
    {
        return DunningBlock::create(array_merge([
            'organization_id' => $this->organization->id,
            'contact_id'      => $contact->id,
            'reason'          => 'Payment plan agreed',
            'blocked_by'      => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Dunning Levels — Index
    // -------------------------------------------------------------------------

    public function test_index_levels_returns_list(): void
    {
        $this->makeLevel();
        $this->makeLevel();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/dunning/levels');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_levels_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/dunning/levels');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Dunning Levels — Store
    // -------------------------------------------------------------------------

    public function test_store_level_creates_dunning_level(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/dunning/levels', [
                'level_number'      => 1,
                'name'              => 'First Notice',
                'days_overdue_from' => 30,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_level_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/dunning/levels', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Dunning Levels — Update
    // -------------------------------------------------------------------------

    public function test_update_level_modifies_level(): void
    {
        $level = $this->makeLevel(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/dunning/levels/' . $level->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $level->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Dunning Levels — Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_level_soft_deletes(): void
    {
        $level = $this->makeLevel();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/dunning/levels/' . $level->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('dunning_levels', ['id' => $level->id]);
    }

    // -------------------------------------------------------------------------
    // Dunning Runs
    // -------------------------------------------------------------------------

    public function test_run_dunning_validates_run_date(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/dunning/run', []);

        $response->assertStatus(422);
    }

    public function test_index_runs_returns_list(): void
    {
        $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/dunning/runs');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_run_returns_details(): void
    {
        $run = $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/dunning/runs/' . $run->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $run->id);
    }

    // -------------------------------------------------------------------------
    // Dunning Blocks
    // -------------------------------------------------------------------------

    public function test_index_blocks_returns_list(): void
    {
        $contact = $this->makeContact();
        $this->makeBlock($contact);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/dunning/blocks');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_create_block_places_block_on_contact(): void
    {
        $contact = $this->makeContact();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/dunning/blocks/contacts/' . $contact->uuid, [
                'reason' => 'Disputed invoice',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_create_block_validates_reason_required(): void
    {
        $contact = $this->makeContact();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/dunning/blocks/contacts/' . $contact->uuid, []);

        $response->assertStatus(422);
    }

    public function test_release_block_releases_active_block(): void
    {
        $contact = $this->makeContact();
        $block   = $this->makeBlock($contact);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/dunning/blocks/' . $block->uuid . '/release', [
                'release_reason' => 'Payment received',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotNull($block->fresh()->released_at);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/dunning/levels')->assertStatus(401);
    }
}
