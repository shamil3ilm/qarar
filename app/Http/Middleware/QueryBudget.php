<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tracks how many DB queries are executed per request.
 * Logs a warning when the budget is exceeded.
 * In non-production, can also set a hard cap to fail loudly.
 */
class QueryBudget
{
    public function handle(Request $request, Closure $next, int $budget = 30): Response
    {
        $queryCount = 0;
        $queries    = [];

        DB::listen(function ($query) use (&$queryCount, &$queries, $budget) {
            $queryCount++;
            if ($queryCount <= $budget + 5) { // capture slightly beyond budget for context
                $queries[] = [
                    'sql'  => $query->sql,
                    'time' => $query->time,
                ];
            }
        });

        $response = $next($request);

        if ($queryCount > $budget) {
            $context = [
                'url'         => $request->fullUrl(),
                'method'      => $request->method(),
                'query_count' => $queryCount,
                'budget'      => $budget,
                'user_id'     => $request->user()?->id,
                'org_id'      => $request->user()?->organization_id,
                'queries'     => array_slice($queries, 0, 10), // first 10 for context
            ];

            Log::channel('slow_queries')->warning('Query budget exceeded', $context);
        }

        $response->headers->set('X-Query-Count', (string) $queryCount);

        return $response;
    }
}
