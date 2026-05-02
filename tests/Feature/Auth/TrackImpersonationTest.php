<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\TrackImpersonation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class TrackImpersonationTest extends TestCase
{
    public function test_middleware_sets_impersonation_attributes_when_claims_present(): void
    {
        $sessionId = 'test-session-uuid-1234';
        $adminId   = 99;

        // Bind a mock JWT guard into the auth manager so auth('api')->payload() works
        $payloadMock = \Mockery::mock(\PHPOpenSourceSaver\JWTAuth\Payload::class);
        $payloadMock->shouldReceive('get')->with('is_impersonating')->andReturn(true);
        $payloadMock->shouldReceive('get')->with('impersonated_by_id')->andReturn($adminId);
        $payloadMock->shouldReceive('get')->with('impersonation_session_id')->andReturn($sessionId);

        $guardMock = \Mockery::mock(\PHPOpenSourceSaver\JWTAuth\JWTGuard::class);
        $guardMock->shouldReceive('payload')->andReturn($payloadMock);

        \Illuminate\Support\Facades\Auth::extend('jwt-test', fn() => $guardMock);
        config(['auth.guards.api' => ['driver' => 'jwt-test', 'provider' => 'users']]);
        // Reset the resolved guard so the new driver is used
        \Illuminate\Support\Facades\Auth::forgetGuards();

        $request    = new Request();
        $response   = new Response();
        $middleware = new TrackImpersonation();

        $result = $middleware->handle($request, fn() => $response);

        $this->assertSame($adminId, $request->attributes->get('impersonated_by_id'));
        $this->assertSame($sessionId, $request->attributes->get('impersonation_session_id'));
        $this->assertSame($response, $result);
    }

    public function test_middleware_skips_silently_when_no_token(): void
    {
        $request    = new Request();
        $response   = new Response();
        $middleware = new TrackImpersonation();

        $result = $middleware->handle($request, fn() => $response);

        $this->assertNull($request->attributes->get('impersonated_by_id'));
        $this->assertNull($request->attributes->get('impersonation_session_id'));
        $this->assertSame($response, $result);
    }
}
