<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\ApprovedVendorList;
use App\Models\Manufacturing\SupplierNcrRecord;
use App\Models\Manufacturing\SupplierQualityRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupplierQualityController extends Controller
{
    public function ratings(Request $request): JsonResponse
    {
        $ratings = SupplierQualityRating::where('organization_id', $request->user()->organization_id)
            ->with('supplier')
            ->paginate(20);

        return $this->paginated($ratings);
    }

    public function storeRating(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'          => 'required|integer|exists:contacts,id',
            'rating_period_start'  => 'required|date',
            'rating_period_end'    => 'required|date|after_or_equal:rating_period_start',
            'quality_score'        => 'nullable|numeric|min:0|max:100',
            'delivery_score'       => 'nullable|numeric|min:0|max:100',
            'price_score'          => 'nullable|numeric|min:0|max:100',
            'overall_score'        => 'nullable|numeric|min:0|max:100',
            'classification'       => 'required|in:preferred,approved,conditional,disqualified',
            'notes'                => 'nullable|string',
        ]);

        $data['uuid']            = (string) Str::uuid();
        $data['organization_id'] = $request->user()->organization_id;
        $data['evaluated_by_id'] = $request->user()->id;

        $rating = SupplierQualityRating::create($data);

        return $this->created($rating->load('supplier'));
    }

    public function avl(Request $request): JsonResponse
    {
        $avl = ApprovedVendorList::where('organization_id', $request->user()->organization_id)
            ->with(['supplier', 'product'])
            ->paginate(20);

        return $this->paginated($avl);
    }

    public function storeAvl(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'           => 'required|integer|exists:contacts,id',
            'product_id'            => 'nullable|integer|exists:products,id',
            'approved_date'         => 'required|date',
            'expiry_date'           => 'nullable|date|after:approved_date',
            'approval_conditions'   => 'nullable|string',
        ]);

        $data['uuid']            = (string) Str::uuid();
        $data['organization_id'] = $request->user()->organization_id;

        $avl = ApprovedVendorList::create($data);

        return $this->created($avl->load(['supplier', 'product']));
    }

    public function ncrs(Request $request): JsonResponse
    {
        $ncrs = SupplierNcrRecord::where('organization_id', $request->user()->organization_id)
            ->with(['supplier', 'product'])
            ->paginate(20);

        return $this->paginated($ncrs);
    }

    public function storeNcr(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ncr_number'                    => 'required|string|max:50|unique:supplier_ncr_records',
            'supplier_id'                   => 'required|integer|exists:contacts,id',
            'product_id'                    => 'nullable|integer|exists:products,id',
            'po_number'                     => 'nullable|string|max:50',
            'nonconformance_description'    => 'required|string',
            'severity'                      => 'required|in:critical,major,minor',
            'detected_date'                 => 'required|date',
        ]);

        $data['uuid']            = (string) Str::uuid();
        $data['organization_id'] = $request->user()->organization_id;

        $ncr = SupplierNcrRecord::create($data);

        return $this->created($ncr->load(['supplier', 'product']));
    }

    public function closeNcr(Request $request, int $id): JsonResponse
    {
        $ncr  = SupplierNcrRecord::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $data = $request->validate([
            'disposition' => 'required|in:use_as_is,rework,repair,return_to_supplier,scrap',
        ]);

        $ncr->update(array_merge($data, [
            'status'      => 'closed',
            'closed_date' => now()->toDateString(),
        ]));

        return $this->success($ncr, 'NCR closed');
    }
}
