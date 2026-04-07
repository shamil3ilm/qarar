<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\Complaint;
use App\Models\Manufacturing\ComplaintResolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $complaints = Complaint::where('organization_id', $request->user()->organization_id)
            ->with(['contact', 'assignedTo'])
            ->paginate(20);

        return $this->paginated($complaints);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'complaint_number'        => 'required|string|max:50|unique:complaints',
            'complaint_source'        => 'required|in:customer,internal,regulatory,supplier',
            'contact_id'              => 'nullable|integer|exists:contacts,id',
            'subject'                 => 'required|string|max:255',
            'description'             => 'required|string',
            'priority'                => 'required|in:critical,high,medium,low',
            'assigned_to_id'          => 'nullable|integer|exists:users,id',
            'received_date'           => 'required|date',
            'target_resolution_date'  => 'nullable|date',
        ]);

        $data['uuid']            = (string) Str::uuid();
        $data['organization_id'] = $request->user()->organization_id;

        $complaint = Complaint::create($data);

        return $this->created($complaint);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $complaint = Complaint::where('organization_id', $request->user()->organization_id)
            ->with(['contact', 'assignedTo', 'communications', 'resolutions'])
            ->findOrFail($id);

        return $this->success($complaint);
    }

    public function addCommunication(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'direction' => 'required|in:inbound,outbound',
            'channel'   => 'required|in:email,phone,letter,portal,in_person',
            'content'   => 'required|string',
        ]);

        $complaint = Complaint::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $comm      = $complaint->communications()->create(array_merge($data, [
            'uuid'             => (string) Str::uuid(),
            'user_id'          => $request->user()->id,
            'communicated_at'  => now(),
        ]));

        return $this->created($comm);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'resolution_type'         => 'required|in:replacement,refund,credit,repair,explanation,apology,other',
            'resolution_description'  => 'required|string',
            'customer_accepted'       => 'boolean',
        ]);

        $complaint  = Complaint::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $resolution = ComplaintResolution::create(array_merge($data, [
            'uuid'            => (string) Str::uuid(),
            'complaint_id'    => $complaint->id,
            'resolution_date' => now()->toDateString(),
            'resolved_by_id'  => $request->user()->id,
        ]));

        $complaint->update([
            'status'                  => 'resolved',
            'actual_resolution_date'  => now()->toDateString(),
        ]);

        return $this->created($resolution);
    }
}
