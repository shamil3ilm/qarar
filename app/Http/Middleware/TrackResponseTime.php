<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackResponseTime
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);
        $response = $next($request);
        $ms = (int) round((hrtime(true) - $start) / 1_000_000);

        $response->headers->set('X-Response-Time', $ms . 'ms');

        $budget = (int) config('erp.performance.response_budget_ms', 300);
        if ($ms > $budget) {
            Log::channel('slow_queries')->warning('Slow response', [
                'url'         => $request->fullUrl(),
                'method'      => $request->method(),
                'duration_ms' => $ms,
                'budget_ms'   => $budget,
                'user_id'     => $request->user()?->id,
                'org_id'      => $request->user()?->organization_id,
            ]);
        }

        return $response;
    }
}
