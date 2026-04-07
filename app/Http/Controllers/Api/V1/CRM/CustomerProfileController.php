<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\CRM;

use App\Http\Controllers\Controller;
use App\Services\CRM\CustomerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerProfileController extends Controller
{
    public function __construct(private readonly CustomerProfileService $service) {}

    /**
     * GET /crm/customers/{contact}/360
     *
     * Returns a 360° consolidated view of a customer: financials, opportunities,
     * activities, and service tickets.
     */
    public function show(Request $request, int $contactId): JsonResponse
    {
        $profile = $this->service->getProfile(
            $request->user()->organization_id,
            $contactId
        );

        return $this->success($profile);
    }
}
