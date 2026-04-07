<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Trade;

use App\Http\Controllers\Controller;
use App\Models\Trade\Incoterm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncotermController extends Controller
{
    /**
     * List incoterms.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Incoterm::query()->orderBy('code')
            ->when($request->boolean('active_only', true), fn($q) => $q->active())
            ->when($request->has('version'), fn($q) => $q->forVersion($request->input('version')), fn($q) => $q->latestVersion())
            ->when($request->has('transport_mode'), fn($q) => $request->input('transport_mode') === 'sea' ? $q->forSeaTransport() : $q->forAllTransport());

        $incoterms = $query->get();

        return $this->success($incoterms);
    }

    /**
     * Show a single incoterm.
     */
    public function show(Incoterm $incoterm): JsonResponse
    {
        return $this->success($incoterm);
    }
}
