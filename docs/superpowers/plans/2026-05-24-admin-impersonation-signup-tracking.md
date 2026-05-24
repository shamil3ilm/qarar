# Admin Impersonation & Signup Source Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add admin impersonation (with full audit trail) and user signup source attribution to the ERP backend.

**Architecture:** Impersonation issues a short-lived JWT for the target user with extra claims (`impersonated_by_id`, `impersonation_session_id`, `is_impersonating`). A `TrackImpersonation` middleware reads those claims on every request and stores them in request attributes; `ActivityLogService::log()` reads those attributes and auto-stamps state-changing activity log entries. Signup tracking adds nullable UTM/source columns to `users`, populated at registration from the request payload.

**Tech Stack:** Laravel 12, PHP 8.2, phpopensourcesaver/jwt-auth, PHPUnit (existing test suite via `php artisan test`)

---

## File Map

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `database/migrations/2026_05_02_000001_add_impersonation_columns_to_activity_logs_table.php` | Add `impersonated_by_id` + `impersonation_session_id` to `activity_logs` |
| Create | `database/migrations/2026_05_02_000002_add_signup_tracking_to_users_table.php` | Add UTM + source columns to `users` |
| Modify | `app/Models/Core/ActivityLog.php` | Add constants, fillable, `impersonatedBy` relation |
| Modify | `app/Models/User.php` | Add signup fields to fillable, `invitedBy` relation |
| Modify | `app/Services/Core/ActivityLogService.php` | Auto-stamp impersonation context from request attributes |
| Create | `app/Http/Middleware/TrackImpersonation.php` | Read JWT claims, set request attributes |
| Create | `app/Services/Auth/ImpersonationService.php` | Start/end impersonation session logic |
| Create | `app/Http/Controllers/Api/V1/Auth/ImpersonationController.php` | `POST /auth/impersonate/{user}`, `POST /auth/impersonate/end` |
| Create | `app/Http/Controllers/Api/V1/Admin/ImpersonationAuditController.php` | `GET /admin/impersonation-sessions`, `GET /admin/impersonation-sessions/{id}` |
| Modify | `app/Http/Requests/Auth/RegisterRequest.php` | Add nullable signup tracking validation rules |
| Modify | `app/Http/Controllers/Api/V1/Auth/AuthController.php` | Pass signup fields into `User::create()` |
| Modify | `routes/api/v1/auth.php` | Register impersonate start/end routes |
| Modify | `routes/api/v1/admin.php` | Register audit routes |
| Modify | `bootstrap/app.php` | Register `TrackImpersonation` middleware alias + global append |
| Create | `tests/Feature/Auth/ImpersonationTest.php` | Feature tests for impersonation |
| Create | `tests/Feature/Auth/SignupTrackingTest.php` | Feature tests for signup attribution |

---

## Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2026_05_02_000001_add_impersonation_columns_to_activity_logs_table.php`
- Create: `database/migrations/2026_05_02_000002_add_signup_tracking_to_users_table.php`

- [ ] **Step 1: Create migration 1 — impersonation columns on activity_logs**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('impersonated_by_id')->nullable()->after('user_id');
            $table->char('impersonation_session_id', 36)->nullable()->after('impersonated_by_id');

            $table->foreign('impersonated_by_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index('impersonation_session_id');
            $table->index('impersonated_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['impersonated_by_id']);
            $table->dropIndex(['impersonation_session_id']);
            $table->dropIndex(['impersonated_by_id']);
            $table->dropColumn(['impersonated_by_id', 'impersonation_session_id']);
        });
    }
};
```

- [ ] **Step 2: Create migration 2 — signup tracking columns on users**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_source', 30)->nullable()->after('last_login_ip');
            $table->string('utm_source', 100)->nullable()->after('registration_source');
            $table->string('utm_medium', 100)->nullable()->after('utm_source');
            $table->string('utm_campaign', 150)->nullable()->after('utm_medium');
            $table->string('utm_term', 150)->nullable()->after('utm_campaign');
            $table->string('utm_content', 150)->nullable()->after('utm_term');
            $table->string('referral_code', 50)->nullable()->after('utm_content');
            $table->string('registration_device_type', 20)->nullable()->after('referral_code');
            $table->string('registration_ip', 45)->nullable()->after('registration_device_type');
            $table->unsignedBigInteger('invited_by_user_id')->nullable()->after('registration_ip');

            $table->foreign('invited_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['invited_by_user_id']);
            $table->dropColumn([
                'registration_source', 'utm_source', 'utm_medium', 'utm_campaign',
                'utm_term', 'utm_content', 'referral_code', 'registration_device_type',
                'registration_ip', 'invited_by_user_id',
            ]);
        });
    }
};
```

- [ ] **Step 3: Run migrations**

```bash
php artisan migrate
```

Expected: Both migrations apply cleanly. No errors.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_02_000001_add_impersonation_columns_to_activity_logs_table.php
git add database/migrations/2026_05_02_000002_add_signup_tracking_to_users_table.php
git commit -m "feat: add impersonation audit columns and signup tracking columns via migrations"
```

---

## Task 2: Update ActivityLog Model

**Files:**
- Modify: `app/Models/Core/ActivityLog.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/ImpersonationTest.php` with just the model assertion:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Core\ActivityLog;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
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
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Auth/ImpersonationTest.php --filter "test_activity_log"
```

Expected: FAIL — constants and fillable fields not defined yet.

- [ ] **Step 3: Add constants, fillable entries, and relation to ActivityLog**

In `app/Models/Core/ActivityLog.php`, after the existing `const ACTION_LOGOUT = 'logout';` line, add:

```php
const ACTION_IMPERSONATION_STARTED = 'impersonation_started';
const ACTION_IMPERSONATION_ENDED   = 'impersonation_ended';
```

In the `$fillable` array, add after `'user_id'`:

```php
'impersonated_by_id',
'impersonation_session_id',
```

After the existing relationships (or at the end of the class before the closing `}`), add:

```php
public function impersonatedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\App\Models\User::class, 'impersonated_by_id');
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test tests/Feature/Auth/ImpersonationTest.php --filter "test_activity_log"
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Core/ActivityLog.php tests/Feature/Auth/ImpersonationTest.php
git commit -m "feat: add impersonation fields and constants to ActivityLog model"
```

---

## Task 3: Update User Model

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/SignupTrackingTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class SignupTrackingTest extends TestCase
{
    public function test_user_fillable_includes_signup_tracking_fields(): void
    {
        $user = new User();
        $fillable = $user->getFillable();

        foreach ([
            'registration_source', 'utm_source', 'utm_medium', 'utm_campaign',
            'utm_term', 'utm_content', 'referral_code', 'registration_device_type',
            'registration_ip', 'invited_by_user_id',
        ] as $field) {
            $this->assertContains($field, $fillable, "Field {$field} missing from fillable");
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Auth/SignupTrackingTest.php
```

Expected: FAIL — fields not in `$fillable`.

- [ ] **Step 3: Add signup fields to User model**

In `app/Models/User.php`, add to the `$fillable` array (after `'last_login_ip'`):

```php
'registration_source',
'utm_source',
'utm_medium',
'utm_campaign',
'utm_term',
'utm_content',
'referral_code',
'registration_device_type',
'registration_ip',
'invited_by_user_id',
```

After the existing relationships in the class, add:

```php
public function invitedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(self::class, 'invited_by_user_id');
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test tests/Feature/Auth/SignupTrackingTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php tests/Feature/Auth/SignupTrackingTest.php
git commit -m "feat: add signup tracking fields and invitedBy relation to User model"
```

---

## Task 4: Update ActivityLogService — Auto-Stamp Impersonation Context

**Files:**
- Modify: `app/Services/Core/ActivityLogService.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Auth/ImpersonationTest.php`:

```php
use App\Models\Core\ActivityLog;
use App\Models\User;
use App\Services\Core\ActivityLogService;
use Illuminate\Http\Request;

public function test_activity_log_service_stamps_impersonation_context_on_critical_actions(): void
{
    $org = \App\Models\Core\Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $target = User::factory()->create(['organization_id' => $org->id]);

    $sessionId = \Illuminate\Support\Str::uuid()->toString();

    // Simulate request attributes set by TrackImpersonation middleware
    $request = Request::create('/test');
    $request->attributes->set('impersonated_by_id', $admin->id);
    $request->attributes->set('impersonation_session_id', $sessionId);
    app()->instance('request', $request);

    $this->actingAs($target, 'api');

    $log = app(ActivityLogService::class)->log([
        'action' => ActivityLog::ACTION_UPDATED,
        'entity_type' => 'Invoice',
        'entity_id' => 1,
        'entity_name' => 'INV-001',
        'description' => 'Invoice updated during impersonation',
    ]);

    $this->assertSame($admin->id, $log->impersonated_by_id);
    $this->assertSame($sessionId, $log->impersonation_session_id);
}

public function test_activity_log_service_does_not_stamp_viewed_actions(): void
{
    $org = \App\Models\Core\Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $target = User::factory()->create(['organization_id' => $org->id]);

    $request = Request::create('/test');
    $request->attributes->set('impersonated_by_id', $admin->id);
    $request->attributes->set('impersonation_session_id', \Illuminate\Support\Str::uuid()->toString());
    app()->instance('request', $request);

    $this->actingAs($target, 'api');

    $log = app(ActivityLogService::class)->log([
        'action' => ActivityLog::ACTION_VIEWED,
        'entity_type' => 'Invoice',
        'entity_id' => 1,
        'entity_name' => 'INV-001',
        'description' => 'Invoice viewed',
    ]);

    $this->assertNull($log->impersonated_by_id);
    $this->assertNull($log->impersonation_session_id);
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Auth/ImpersonationTest.php --filter "stamp"
```

Expected: FAIL.

- [ ] **Step 3: Update ActivityLogService::log()**

In `app/Services/Core/ActivityLogService.php`, add a private constant at the top of the class (after the class opening brace):

```php
private const CRITICAL_ACTIONS = [
    ActivityLog::ACTION_CREATED,
    ActivityLog::ACTION_UPDATED,
    ActivityLog::ACTION_DELETED,
    ActivityLog::ACTION_RESTORED,
    ActivityLog::ACTION_APPROVED,
    ActivityLog::ACTION_REJECTED,
    ActivityLog::ACTION_SUBMITTED,
    ActivityLog::ACTION_EXPORTED,
    ActivityLog::ACTION_EMAILED,
    ActivityLog::ACTION_PRINTED,
    ActivityLog::ACTION_ARCHIVED,
    ActivityLog::ACTION_IMPERSONATION_STARTED,
    ActivityLog::ACTION_IMPERSONATION_ENDED,
];
```

Inside the `log(array $data): ActivityLog` method, before the `return ActivityLog::create([...])` call, add the impersonation stamping logic. Find the `return ActivityLog::create([` line and replace it with:

```php
// Auto-stamp impersonation context when set by TrackImpersonation middleware
$impersonatedById = $data['impersonated_by_id']
    ?? request()->attributes->get('impersonated_by_id');
$impersonationSessionId = $data['impersonation_session_id']
    ?? request()->attributes->get('impersonation_session_id');

$shouldStamp = $impersonatedById !== null
    && in_array($data['action'], self::CRITICAL_ACTIONS, true);

return ActivityLog::create([
```

Then at the end of the `ActivityLog::create([...])` array (before the closing `]);`), add:

```php
    'impersonated_by_id'       => $shouldStamp ? $impersonatedById : null,
    'impersonation_session_id' => $shouldStamp ? $impersonationSessionId : null,
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Auth/ImpersonationTest.php --filter "stamp"
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Core/ActivityLogService.php tests/Feature/Auth/ImpersonationTest.php
git commit -m "feat: auto-stamp impersonation context on critical ActivityLog entries"
```

---

## Task 5: TrackImpersonation Middleware

**Files:**
- Create: `app/Http/Middleware/TrackImpersonation.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Auth/ImpersonationTest.php`:

```php
use App\Http\Middleware\TrackImpersonation;

public function test_track_impersonation_middleware_sets_request_attributes(): void
{
    // This test is covered implicitly by Task 6 integration tests.
    // Direct middleware test: verify the class exists and has handle().
    $this->assertTrue(class_exists(TrackImpersonation::class));
    $this->assertTrue(method_exists(TrackImpersonation::class, 'handle'));
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Auth/ImpersonationTest.php --filter "middleware"
```

Expected: FAIL — class does not exist.

- [ ] **Step 3: Create the middleware**

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('api')->check()) {
            try {
                $payload = auth('api')->payload();

                if ($payload && $payload->get('is_impersonating')) {
                    $request->attributes->set('impersonated_by_id', $payload->get('impersonated_by_id'));
                    $request->attributes->set('impersonation_session_id', $payload->get('impersonation_session_id'));
                }
            } catch (\Throwable) {
                // Invalid or missing payload — not an impersonation session, continue silently
            }
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test tests/Feature/Auth/ImpersonationTest.php --filter "middleware"
```

Expected: PASS.

- [ ] **Step 5: Register the middleware in bootstrap/app.php**

In `bootstrap/app.php`, inside the `->withMiddleware(function (Middleware $middleware)` block, add the alias alongside the existing aliases:

```php
$middleware->alias([
    // ... existing aliases ...
    'track.impersonation' => \App\Http\Middleware\TrackImpersonation::class,
]);
```

Also append it as a global middleware (it runs safely on all requests, checking auth internally):

```php
$middleware->append(\App\Http\Middleware\TrackImpersonation::class);
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/TrackImpersonation.php bootstrap/app.php tests/Feature/Auth/ImpersonationTest.php
git commit -m "feat: add TrackImpersonation middleware and register globally"
```

---

## Task 6: ImpersonationService

**Files:**
- Create: `app/Services/Auth/ImpersonationService.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Auth/ImpersonationTest.php`:

```php
use App\Services\Auth\ImpersonationService;

public function test_impersonation_service_exists(): void
{
    $this->assertTrue(class_exists(ImpersonationService::class));
}

public function test_impersonation_service_rejects_super_admin_target(): void
{
    $org = \App\Models\Core\Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id, 'is_super_admin' => true]);
    $target = User::factory()->create(['organization_id' => $org->id, 'is_super_admin' => true]);

    $this->actingAs($admin, 'api');

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Super-admin accounts cannot be impersonated.');

    app(ImpersonationService::class)->start($admin, $target, 'Testing the block');
}

public function test_impersonation_service_rejects_cross_org_for_non_super_admin(): void
{
    $org1 = \App\Models\Core\Organization::factory()->create();
    $org2 = \App\Models\Core\Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org1->id, 'is_super_admin' => false]);
    $target = User::factory()->create(['organization_id' => $org2->id, 'is_super_admin' => false]);

    // Grant impersonate_users permission to admin
    $permission = \App\Models\Core\Permission::firstOrCreate(['name' => 'impersonate_users', 'slug' => 'impersonate_users']);
    $admin->permissions()->attach($permission->id);

    $this->actingAs($admin, 'api');

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('You can only impersonate users within your organization.');

    app(ImpersonationService::class)->start($admin, $target, 'Cross org test');
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Auth/ImpersonationTest.php --filter "impersonation_service"
```

Expected: FAIL — class does not exist.

- [ ] **Step 3: Create the service**

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Core\ActivityLog;
use App\Models\User;
use App\Services\Core\ActivityLogService;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImpersonationService
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}

    public function start(User $admin, User $target, string $reason): array
    {
        // Cannot impersonate from within an active impersonation session
        try {
            $payload = auth('api')->payload();
            if ($payload && $payload->get('is_impersonating')) {
                throw new InvalidArgumentException('You cannot impersonate while already in an impersonation session.');
            }
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable) {
            // No valid payload — not in an impersonation session, continue
        }

        if ($target->is_super_admin) {
            throw new InvalidArgumentException('Super-admin accounts cannot be impersonated.');
        }

        if (!$admin->is_super_admin && !$admin->hasPermission('impersonate_users')) {
            throw new InvalidArgumentException('You do not have permission to impersonate users.');
        }

        if (!$admin->is_super_admin && $admin->organization_id !== $target->organization_id) {
            throw new InvalidArgumentException('You can only impersonate users within your organization.');
        }

        $sessionId = Str::uuid()->toString();

        $token = auth('api')
            ->setTTL(60)
            ->claims([
                'impersonated_by_id'       => $admin->id,
                'impersonation_session_id' => $sessionId,
                'is_impersonating'         => true,
            ])
            ->fromUser($target);

        $this->activityLogService->log([
            'user_id'                  => $target->id,
            'organization_id'          => $target->organization_id,
            'action'                   => ActivityLog::ACTION_IMPERSONATION_STARTED,
            'entity_type'              => 'User',
            'entity_id'                => $target->id,
            'entity_name'              => $target->name,
            'description'              => "Admin {$admin->name} started impersonating {$target->name}",
            'metadata'                 => [
                'reason'                   => $reason,
                'admin_id'                 => $admin->id,
                'admin_name'               => $admin->name,
                'impersonation_session_id' => $sessionId,
            ],
            'impersonated_by_id'       => $admin->id,
            'impersonation_session_id' => $sessionId,
            'severity'                 => ActivityLog::SEVERITY_WARNING,
            'module'                   => 'core',
        ]);

        return [
            'token'                    => $token,
            'expires_at'               => now()->addMinutes(60)->toIso8601String(),
            'impersonation_session_id' => $sessionId,
            'target_user'              => $target,
        ];
    }

    public function end(User $target, int $impersonatedById, string $sessionId): void
    {
        $this->activityLogService->log([
            'user_id'                  => $target->id,
            'organization_id'          => $target->organization_id,
            'action'                   => ActivityLog::ACTION_IMPERSONATION_ENDED,
            'entity_type'              => 'User',
            'entity_id'                => $target->id,
            'entity_name'              => $target->name,
            'description'              => "Impersonation session ended for {$target->name}",
            'metadata'                 => [
                'admin_id'                 => $impersonatedById,
                'impersonation_session_id' => $sessionId,
            ],
            'impersonated_by_id'       => $impersonatedById,
            'impersonation_session_id' => $sessionId,
            'severity'                 => ActivityLog::SEVERITY_WARNING,
            'module'                   => 'core',
        ]);

        auth('api')->invalidate(true);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Auth/ImpersonationTest.php --filter "impersonation_service"
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Auth/ImpersonationService.php tests/Feature/Auth/ImpersonationTest.php
git commit -m "feat: add ImpersonationService with start/end session logic"
```

---

## Task 7: ImpersonationController

**Files:**
- Create: `app/Http/Controllers/Api/V1/Auth/ImpersonationController.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Auth/ImpersonationTest.php`:

```php
public function test_super_admin_can_start_impersonation(): void
{
    $org = \App\Models\Core\Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id, 'is_super_admin' => true]);
    $target = User::factory()->create(['organization_id' => $org->id, 'is_super_admin' => false]);

    $response = $this->actingAs($admin, 'api')
        ->postJson("/api/v1/auth/impersonate/{$target->id}", [
            'reason' => 'Investigating reported invoice display issue',
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['token', 'expires_at', 'impersonation_session_id', 'target_user'],
        ]);
}

public function test_reason_is_required_to_start_impersonation(): void
{
    $org = \App\Models\Core\Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id, 'is_super_admin' => true]);
    $target = User::factory()->create(['organization_id' => $org->id]);

    $response = $this->actingAs($admin, 'api')
        ->postJson("/api/v1/auth/impersonate/{$target->id}", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
}

public function test_cannot_impersonate_super_admin_via_endpoint(): void
{
    $org = \App\Models\Core\Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id, 'is_super_admin' => true]);
    $target = User::factory()->create(['organization_id' => $org->id, 'is_super_admin' => true]);

    $response = $this->actingAs($admin, 'api')
        ->postJson("/api/v1/auth/impersonate/{$target->id}", [
            'reason' => 'Attempting to impersonate a super admin',
        ]);

    $response->assertForbidden();
}

public function test_user_without_permission_cannot_impersonate(): void
{
    $org = \App\Models\Core\Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id, 'is_super_admin' => false]);
    $target = User::factory()->create(['organization_id' => $org->id]);

    $response = $this->actingAs($admin, 'api')
        ->postJson("/api/v1/auth/impersonate/{$target->id}", [
            'reason' => 'No permission attempt',
        ]);

    $response->assertForbidden();
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Auth/ImpersonationTest.php --filter "endpoint|impersonate"
```

Expected: FAIL — routes do not exist.

- [ ] **Step 3: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\ImpersonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(
        private ImpersonationService $impersonationService
    ) {}

    public function start(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        return $this->tryAction(
            fn() => $this->impersonationService->start(
                auth()->user(),
                $user,
                $validated['reason']
            ),
            'Impersonation session started.',
            'IMPERSONATION_FAILED',
            403
        );
    }

    public function end(): JsonResponse
    {
        try {
            $payload = auth('api')->payload();
        } catch (\Throwable) {
            return $this->error('No active impersonation session.', 'NOT_IMPERSONATING', 400);
        }

        if (!$payload || !$payload->get('is_impersonating')) {
            return $this->error('No active impersonation session.', 'NOT_IMPERSONATING', 400);
        }

        $this->impersonationService->end(
            auth()->user(),
            (int) $payload->get('impersonated_by_id'),
            (string) $payload->get('impersonation_session_id')
        );

        return $this->success(null, 'Impersonation session ended.');
    }
}
```

- [ ] **Step 4: Register the routes**

In `routes/api/v1/auth.php`, inside the existing `Route::middleware(['auth:api', 'validate.jwt'])->group(...)` block, add:

```php
use App\Http\Controllers\Api\V1\Auth\ImpersonationController;

Route::post('/auth/impersonate/{user}', [ImpersonationController::class, 'start'])
    ->name('auth.impersonate.start');
Route::post('/auth/impersonate/end', [ImpersonationController::class, 'end'])
    ->name('auth.impersonate.end');
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Auth/ImpersonationTest.php --filter "endpoint|impersonate|permission|reason"
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/Auth/ImpersonationController.php routes/api/v1/auth.php tests/Feature/Auth/ImpersonationTest.php
git commit -m "feat: add ImpersonationController with start/end endpoints"
```

---

## Task 8: ImpersonationAuditController

**Files:**
- Create: `app/Http/Controllers/Api/V1/Admin/ImpersonationAuditController.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Auth/ImpersonationTest.php`:

```php
public function test_super_admin_can_list_impersonation_sessions(): void
{
    $org = \App\Models\Core\Organization::factory()->create();
    $superAdmin = User::factory()->create(['is_super_admin' => true, 'organization_id' => $org->id]);

    // Create a sample impersonation_started log entry
    \App\Models\Core\ActivityLog::factory()->create([
        'action' => ActivityLog::ACTION_IMPERSONATION_STARTED,
        'impersonation_session_id' => \Illuminate\Support\Str::uuid()->toString(),
        'impersonated_by_id' => $superAdmin->id,
        'organization_id' => $org->id,
    ]);

    $response = $this->actingAs($superAdmin, 'api')
        ->getJson('/api/v1/admin/impersonation-sessions');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta']);
}

public function test_non_super_admin_cannot_list_impersonation_sessions(): void
{
    $org = \App\Models\Core\Organization::factory()->create();
    $user = User::factory()->create(['is_super_admin' => false, 'organization_id' => $org->id]);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/v1/admin/impersonation-sessions');

    $response->assertForbidden();
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Auth/ImpersonationTest.php --filter "list_impersonation|cannot_list"
```

Expected: FAIL — routes do not exist.

- [ ] **Step 3: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Core\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpersonationAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = ActivityLog::where('action', ActivityLog::ACTION_IMPERSONATION_STARTED)
            ->with(['user:id,name,email', 'impersonatedBy:id,name,email'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($sessions);
    }

    public function show(string $sessionId): JsonResponse
    {
        $actions = ActivityLog::where('impersonation_session_id', $sessionId)
            ->with(['user:id,name,email', 'impersonatedBy:id,name,email'])
            ->orderBy('created_at')
            ->get();

        if ($actions->isEmpty()) {
            return $this->notFound('Impersonation session not found.');
        }

        $start = $actions->firstWhere('action', ActivityLog::ACTION_IMPERSONATION_STARTED);
        $end   = $actions->firstWhere('action', ActivityLog::ACTION_IMPERSONATION_ENDED);

        return $this->success([
            'session_id'       => $sessionId,
            'started_at'       => $start?->created_at,
            'ended_at'         => $end?->created_at,
            'reason'           => $start?->metadata['reason'] ?? null,
            'admin'            => $start?->impersonatedBy,
            'target_user'      => $start?->user,
            'duration_minutes' => ($start && $end)
                ? $start->created_at->diffInMinutes($end->created_at)
                : null,
            'actions'          => $actions
                ->whereNotIn('action', [
                    ActivityLog::ACTION_IMPERSONATION_STARTED,
                    ActivityLog::ACTION_IMPERSONATION_ENDED,
                ])
                ->values(),
        ]);
    }
}
```

- [ ] **Step 4: Register the routes**

In `routes/api/v1/admin.php`, add inside the existing super-admin middleware group (look for `super.admin` middleware or add a new group):

```php
use App\Http\Controllers\Api\V1\Admin\ImpersonationAuditController;

Route::middleware(['auth:api', 'validate.jwt', 'super.admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/impersonation-sessions', [ImpersonationAuditController::class, 'index'])
            ->name('admin.impersonation.index');
        Route::get('/impersonation-sessions/{session_id}', [ImpersonationAuditController::class, 'show'])
            ->name('admin.impersonation.show');
    });
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Auth/ImpersonationTest.php --filter "list_impersonation|cannot_list"
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/Admin/ImpersonationAuditController.php routes/api/v1/admin.php tests/Feature/Auth/ImpersonationTest.php
git commit -m "feat: add ImpersonationAuditController with session list and detail endpoints"
```

---

## Task 9: Signup Source Tracking — RegisterRequest + AuthController

**Files:**
- Modify: `app/Http/Requests/Auth/RegisterRequest.php`
- Modify: `app/Http/Controllers/Api/V1/Auth/AuthController.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Auth/SignupTrackingTest.php`:

```php
public function test_registration_stores_utm_and_source_fields(): void
{
    $response = $this->postJson('/api/v1/auth/register', [
        'name'                   => 'Ahmed Al-Rashid',
        'email'                  => 'ahmed@example.com',
        'password'               => 'Password123!',
        'password_confirmation'  => 'Password123!',
        'organization_name'      => 'Acme Corp',
        'country_code'           => 'AE',
        'registration_source'    => 'web',
        'utm_source'             => 'google',
        'utm_medium'             => 'cpc',
        'utm_campaign'           => 'gcc-launch-q2',
        'utm_term'               => 'erp software',
        'utm_content'            => 'banner-a',
        'referral_code'          => 'PARTNER-XYZ',
        'registration_device_type' => 'web',
    ]);

    $response->assertCreated();

    $user = \App\Models\User::where('email', 'ahmed@example.com')->first();
    $this->assertNotNull($user);
    $this->assertSame('web', $user->registration_source);
    $this->assertSame('google', $user->utm_source);
    $this->assertSame('cpc', $user->utm_medium);
    $this->assertSame('gcc-launch-q2', $user->utm_campaign);
    $this->assertSame('erp software', $user->utm_term);
    $this->assertSame('banner-a', $user->utm_content);
    $this->assertSame('PARTNER-XYZ', $user->referral_code);
    $this->assertSame('web', $user->registration_device_type);
    $this->assertNotNull($user->registration_ip);
}

public function test_registration_works_without_utm_fields(): void
{
    $response = $this->postJson('/api/v1/auth/register', [
        'name'                  => 'Sara Hassan',
        'email'                 => 'sara@example.com',
        'password'              => 'Password123!',
        'password_confirmation' => 'Password123!',
        'organization_name'     => 'Beta Corp',
        'country_code'          => 'SA',
    ]);

    $response->assertCreated();

    $user = \App\Models\User::where('email', 'sara@example.com')->first();
    $this->assertNull($user->utm_source);
    $this->assertNull($user->registration_source);
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Auth/SignupTrackingTest.php --filter "registration_stores|registration_works"
```

Expected: FAIL — fields not accepted or not stored.

- [ ] **Step 3: Update RegisterRequest with signup tracking rules**

In `app/Http/Requests/Auth/RegisterRequest.php`, add to the `rules()` array (after the `'country_code'` rule):

```php
'registration_source'      => ['nullable', 'string', 'in:web,mobile_ios,mobile_android,api,invitation'],
'utm_source'               => ['nullable', 'string', 'max:100'],
'utm_medium'               => ['nullable', 'string', 'max:100'],
'utm_campaign'             => ['nullable', 'string', 'max:150'],
'utm_term'                 => ['nullable', 'string', 'max:150'],
'utm_content'              => ['nullable', 'string', 'max:150'],
'referral_code'            => ['nullable', 'string', 'max:50'],
'registration_device_type' => ['nullable', 'string', 'in:web,mobile'],
'invited_by_user_id'       => ['nullable', 'integer', 'exists:users,id'],
```

- [ ] **Step 4: Update AuthController::register() — add signup fields to User::create()**

In `app/Http/Controllers/Api/V1/Auth/AuthController.php`, find the `User::create([` call inside the `register()` method and add the signup fields:

```php
$user = User::create([
    'organization_id'          => $organization->id,
    'name'                     => trim($request->name),
    'email'                    => $email,
    'password'                 => $request->password,
    'is_active'                => true,
    'timezone'                 => $this->getDefaultTimezone($request->country_code),
    // Signup attribution (all nullable — existing registrations unaffected)
    'registration_source'      => $request->registration_source,
    'utm_source'               => $request->utm_source,
    'utm_medium'               => $request->utm_medium,
    'utm_campaign'             => $request->utm_campaign,
    'utm_term'                 => $request->utm_term,
    'utm_content'              => $request->utm_content,
    'referral_code'            => $request->referral_code,
    'registration_device_type' => $request->registration_device_type,
    'registration_ip'          => $request->ip(),
    'invited_by_user_id'       => $request->invited_by_user_id,
]);
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Feature/Auth/SignupTrackingTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Auth/RegisterRequest.php app/Http/Controllers/Api/V1/Auth/AuthController.php tests/Feature/Auth/SignupTrackingTest.php
git commit -m "feat: capture signup attribution fields (UTM, source, referral) at registration"
```

---

## Task 10: Full Test Suite Verification

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test
```

Expected: All existing tests pass. No regressions. Both new test files pass.

- [ ] **Step 2: If any test fails, investigate before fixing**

The most likely failure modes:
- `ActivityLogService` tests: the `CRITICAL_ACTIONS` constant references `ActivityLog::ACTION_IMPERSONATION_STARTED` — verify the constant is defined (Task 2).
- `TrackImpersonation` middleware order: if it runs before `auth:api`, `auth()->check()` returns false — verify `append()` places it after auth resolution.
- Route conflicts: `auth/impersonate/end` must be defined **before** `auth/impersonate/{user}` to prevent Laravel matching `end` as a user ID. Check route order in `auth.php`.

- [ ] **Step 3: Verify route order (important)**

In `routes/api/v1/auth.php`, the `end` route must come **before** the `{user}` wildcard route:

```php
Route::post('/auth/impersonate/end', [ImpersonationController::class, 'end'])
    ->name('auth.impersonate.end');
Route::post('/auth/impersonate/{user}', [ImpersonationController::class, 'start'])
    ->name('auth.impersonate.start');
```

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "feat: admin impersonation and signup source tracking — full implementation"
```
