<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\BusinessPartner;
use App\Services\Core\BusinessPartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessPartnerController extends Controller
{
    public function __construct(private readonly BusinessPartnerService $service) {}

    /** GET /business-partners */
    public function index(Request $request): JsonResponse
    {
        $bps = $this->service->list(
            organizationId: $request->user()->organization_id,
            filters:        $request->only(['search', 'role', 'is_active']),
            perPage:        (int) $request->get('per_page', 25),
        );

        return $this->paginatedResponse($bps, 'Business partners retrieved');
    }

    /** POST /business-partners */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'name2'          => ['nullable', 'string', 'max:255'],
            'search_term'    => ['nullable', 'string', 'max:100'],
            'bp_category'    => ['in:ORG,PERSON'],
            'email'          => ['nullable', 'email'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'mobile'         => ['nullable', 'string', 'max:30'],
            'website'        => ['nullable', 'url'],
            'tax_id'         => ['nullable', 'string', 'max:50'],
            'vat_number'     => ['nullable', 'string', 'max:50'],
            'commercial_reg' => ['nullable', 'string', 'max:50'],
            'street'         => ['nullable', 'string'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'max:100'],
            'postal_code'    => ['nullable', 'string', 'max:20'],
            'country'        => ['nullable', 'string', 'size:2'],
            'contact_id'     => ['nullable', 'integer'],
            'supplier_id'    => ['nullable', 'integer'],
            'roles'          => ['array'],
            'roles.*'        => ['string', 'in:FLCU00,FLVN00,BUP001,BUP002'],
            'metadata'       => ['nullable', 'array'],
        ]);

        $bp = $this->service->create($request->user()->organization_id, $data);

        return $this->successResponse($bp, 'Business partner created', 201);
    }

    /** GET /business-partners/{bp} */
    public function show(BusinessPartner $businessPartner): JsonResponse
    {
        return $this->successResponse(
            $businessPartner->load('roles'),
            'Business partner retrieved',
        );
    }

    /** PUT /business-partners/{bp} */
    public function update(Request $request, BusinessPartner $businessPartner): JsonResponse
    {
        $data = $request->validate([
            'name'           => ['string', 'max:255'],
            'name2'          => ['nullable', 'string', 'max:255'],
            'search_term'    => ['nullable', 'string', 'max:100'],
            'email'          => ['nullable', 'email'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'mobile'         => ['nullable', 'string', 'max:30'],
            'website'        => ['nullable', 'url'],
            'tax_id'         => ['nullable', 'string', 'max:50'],
            'vat_number'     => ['nullable', 'string', 'max:50'],
            'commercial_reg' => ['nullable', 'string', 'max:50'],
            'street'         => ['nullable', 'string'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'max:100'],
            'postal_code'    => ['nullable', 'string', 'max:20'],
            'country'        => ['nullable', 'string', 'size:2'],
            'metadata'       => ['nullable', 'array'],
        ]);

        $bp = $this->service->update($businessPartner, $data);

        return $this->successResponse($bp, 'Business partner updated');
    }

    /** POST /business-partners/{bp}/roles */
    public function assignRole(Request $request, BusinessPartner $businessPartner): JsonResponse
    {
        $data = $request->validate([
            'role_code' => ['required', 'string', 'in:FLCU00,FLVN00,BUP001,BUP002'],
        ]);

        $role = $this->service->assignRole($businessPartner, $data['role_code']);

        return $this->successResponse($role, 'Role assigned', 201);
    }

    /** DELETE /business-partners/{bp}/roles/{roleCode} */
    public function revokeRole(BusinessPartner $businessPartner, string $roleCode): JsonResponse
    {
        $this->service->revokeRole($businessPartner, $roleCode);

        return $this->successResponse(null, 'Role revoked');
    }

    /** POST /business-partners/{bp}/merge */
    public function merge(Request $request, BusinessPartner $businessPartner): JsonResponse
    {
        $data = $request->validate([
            'source_id' => ['required', 'integer', 'different:' . $businessPartner->id],
        ]);

        $source = BusinessPartner::findOrFail($data['source_id']);
        $merged = $this->service->merge($source, $businessPartner);

        return $this->successResponse($merged, 'Business partners merged');
    }
}
