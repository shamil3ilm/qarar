<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\CostingVersion;
use App\Services\Manufacturing\StandardCostReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class StandardCostReleaseTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private StandardCostReleaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->service = app(StandardCostReleaseService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // release()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_release_draft_version_makes_it_active(): void
    {
        $version = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_DRAFT,
        ]);

        $released = $this->service->release($version, $this->user->id);

        $this->assertEquals(CostingVersion::STATUS_ACTIVE, $released->status);
    }

    public function test_release_frozen_version_makes_it_active(): void
    {
        $version = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_FROZEN,
        ]);

        $released = $this->service->release($version, $this->user->id);

        $this->assertEquals(CostingVersion::STATUS_ACTIVE, $released->status);
    }

    public function test_release_archives_currently_active_version(): void
    {
        $active = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_ACTIVE,
        ]);

        $new = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_DRAFT,
        ]);

        $this->service->release($new, $this->user->id);

        $this->assertEquals(
            CostingVersion::STATUS_ARCHIVED,
            $active->fresh()->status
        );
    }

    public function test_release_throws_when_already_active(): void
    {
        $version = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_ACTIVE,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be released/');

        $this->service->release($version, $this->user->id);
    }

    public function test_release_throws_when_archived(): void
    {
        $version = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_ARCHIVED,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->release($version, $this->user->id);
    }

    public function test_only_one_active_version_after_release(): void
    {
        // Two pre-existing active versions (edge case)
        CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_ACTIVE,
        ]);

        $new = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_DRAFT,
        ]);

        $this->service->release($new, $this->user->id);

        $activeCount = CostingVersion::where('organization_id', $this->organization->id)
            ->where('status', CostingVersion::STATUS_ACTIVE)
            ->count();

        $this->assertEquals(1, $activeCount);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // freeze()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_freeze_draft_version(): void
    {
        $version = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_DRAFT,
        ]);

        $frozen = $this->service->freeze($version);

        $this->assertEquals(CostingVersion::STATUS_FROZEN, $frozen->status);
    }

    public function test_freeze_throws_if_not_draft(): void
    {
        $version = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_ACTIVE,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->freeze($version);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // rollback()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_rollback_frozen_version_to_active(): void
    {
        CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_ACTIVE,
        ]);

        $prior = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_FROZEN,
        ]);

        $activated = $this->service->rollback($prior, $this->user->id);

        $this->assertEquals(CostingVersion::STATUS_ACTIVE, $activated->status);
    }

    public function test_rollback_throws_if_target_not_frozen(): void
    {
        $version = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_DRAFT,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->rollback($version, $this->user->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // summary()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_summary_returns_product_count(): void
    {
        $version = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => CostingVersion::STATUS_DRAFT,
        ]);

        $summary = $this->service->summary($version);

        $this->assertArrayHasKey('product_count', $summary);
        $this->assertArrayHasKey('avg_total_cost', $summary);
        $this->assertEquals($version->id, $summary['version_id']);
    }
}
