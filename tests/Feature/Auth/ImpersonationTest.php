<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Core\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;
    public function test_activity_log_has_impersonation_constants(): void
    {
        $this->assertSame('impersonation_started', ActivityLog::ACTION_IMPERSONATION_STARTED);
        $this->assertSame('impersonation_ended', ActivityLog::ACTION_IMPERSONATION_ENDED);
    }

    public function test_activity_log_fillable_includes_impersonation_fields(): void
    {
        $log = new ActivityLog();
        $fillable = $log->getFillable();

        $this->assertContains('impersonated_by_id', $fillable);
        $this->assertContains('impersonation_session_id', $fillable);
    }

    public function test_activity_log_service_stamps_impersonation_context_on_critical_actions(): void
    {
        $org = \App\Models\Core\Organization::factory()->create();
        $admin = \App\Models\User::factory()->create(['organization_id' => $org->id]);
        $target = \App\Models\User::factory()->create(['organization_id' => $org->id]);

        $sessionId = \Illuminate\Support\Str::uuid()->toString();

        // Simulate request attributes set by TrackImpersonation middleware
        $request = \Illuminate\Http\Request::create('/test');
        $request->attributes->set('impersonated_by_id', $admin->id);
        $request->attributes->set('impersonation_session_id', $sessionId);
        app()->instance('request', $request);

        $this->actingAs($target, 'api');

        $log = app(\App\Services\Core\ActivityLogService::class)->log([
            'action'      => \App\Models\Core\ActivityLog::ACTION_UPDATED,
            'entity_type' => 'Invoice',
            'entity_id'   => 1,
            'entity_name' => 'INV-001',
            'description' => 'Invoice updated during impersonation',
        ]);

        $this->assertSame($admin->id, $log->impersonated_by_id);
        $this->assertSame($sessionId, $log->impersonation_session_id);
    }

    public function test_activity_log_service_does_not_stamp_viewed_actions(): void
    {
        $org = \App\Models\Core\Organization::factory()->create();
        $admin = \App\Models\User::factory()->create(['organization_id' => $org->id]);
        $target = \App\Models\User::factory()->create(['organization_id' => $org->id]);

        $request = \Illuminate\Http\Request::create('/test');
        $request->attributes->set('impersonated_by_id', $admin->id);
        $request->attributes->set('impersonation_session_id', \Illuminate\Support\Str::uuid()->toString());
        app()->instance('request', $request);

        $this->actingAs($target, 'api');

        $log = app(\App\Services\Core\ActivityLogService::class)->log([
            'action'      => \App\Models\Core\ActivityLog::ACTION_VIEWED,
            'entity_type' => 'Invoice',
            'entity_id'   => 1,
            'entity_name' => 'INV-001',
            'description' => 'Invoice viewed',
        ]);

        $this->assertNull($log->impersonated_by_id);
        $this->assertNull($log->impersonation_session_id);
    }
}
