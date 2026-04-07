<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Activates simulation (dry-run) mode when ?dry_run=true is passed.
 * Services that support simulation check app('simulation.mode').
 * The entire request is wrapped in a DB transaction that is ALWAYS rolled back.
 */
class SimulationMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $isDryRun = filter_var($request->query('dry_run', 'false'), FILTER_VALIDATE_BOOLEAN);

        if (! $isDryRun) {
            return $next($request);
        }

        // Bind a flag so services/actions can check it
        app()->instance('simulation.mode', true);

        // Wrap in a transaction that always rolls back
        $response = null;
        try {
            \Illuminate\Support\Facades\DB::beginTransaction();
            $response = $next($request);
            // Always roll back — this was a simulation
            \Illuminate\Support\Facades\DB::rollBack();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            throw $e;
        }

        // Add header to make simulation visible to callers
        $response->headers->set('X-Simulation-Mode', 'true');
        $response->headers->set('X-Dry-Run', 'true');

        return $response;
    }
}
