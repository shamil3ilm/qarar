<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Core\IpAllowlistService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckIpAllowlist
{
    public function __construct(private IpAllowlistService $ipAllowlistService) {}

    public function handle(Request $request, Closure $next, string $context = 'all'): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $ip    = $request->ip() ?? '';
        $orgId = $user->organization_id;

        if ($orgId === null) {
            return $next($request);
        }

        if (!$this->ipAllowlistService->checkAccess($ip, $orgId, $context)) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'IP_NOT_ALLOWED',
                    'message' => 'Access from your IP address is not permitted.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
