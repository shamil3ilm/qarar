<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Document;

use App\Http\Controllers\Controller;
use App\Models\Document\DigitalSignature;
use App\Models\Document\Document;
use App\Models\Document\DocumentShare;
use App\Services\Document\DocumentVaultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentVaultService $documentVaultService
    ) {
    }

    /**
     * List documents with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $documents = $this->documentVaultService->search(
            $request->only([
                'search', 'folder_id', 'document_type', 'mime_type', 'tags',
                'documentable_type', 'documentable_id', 'is_archived',
                'expiring_within_days', 'sort_by', 'sort_dir',
            ]),
            $request->integer('per_page', 15)
        );

        return $this->paginated($documents);
    }

    /**
     * Upload a new document.
     */
    public function store(Request $request): JsonResponse
    {
        // Decode JSON-encoded tags if sent as string (e.g. from multipart form)
        if ($request->has('tags') && is_string($request->input('tags'))) {
            $decoded = json_decode($request->input('tags'), true);
            if (is_array($decoded)) {
                $request->merge(['tags' => $decoded]);
            }
        }

        $request->validate([
            'file' => 'required|file|max:51200', // 50MB max
            'name' => 'nullable|string|max:255',
            'folder_id' => 'nullable|integer|exists:document_folders,id',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'document_type' => 'nullable|string|in:' . implode(',', Document::DOCUMENT_TYPES),
            'document_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:today',
            'documentable_type' => 'nullable|string|max:255',
            'documentable_id' => 'nullable|integer',
            'access_level' => 'nullable|string|in:' . implode(',', Document::ACCESS_LEVELS),
        ]);

        $document = $this->documentVaultService->upload(
            $request->file('file'),
            $request->except('file'),
            $request->user()->id,
            $request->user()->organization_id
        );

        return $this->created($document->load(['folder', 'uploader']));
    }

    /**
     * Show a document.
     */
    public function show(Document $document): JsonResponse
    {
        // Check private document access
        if ($document->access_level === Document::ACCESS_PRIVATE && $document->uploaded_by !== auth()->id()) {
            return $this->forbidden('You do not have access to this private document.');
        }

        $document->load(['folder', 'uploader', 'versions.uploader', 'signatures']);

        return $this->success($document);
    }

    /**
     * Update a document's metadata.
     */
    public function update(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'document_type' => 'nullable|string|in:' . implode(',', Document::DOCUMENT_TYPES),
            'document_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'access_level' => 'nullable|string|in:' . implode(',', Document::ACCESS_LEVELS),
            'is_archived' => 'nullable|boolean',
        ]);

        $document = $this->documentVaultService->updateDocument($document, $data, $request->user()->id);

        return $this->success($document, 'Document updated successfully.');
    }

    /**
     * Delete a document.
     */
    public function destroy(Request $request, Document $document): JsonResponse
    {
        $this->documentVaultService->deleteDocument($document, $request->user()->id);

        return $this->success(null, 'Document deleted successfully.');
    }

    /**
     * Upload a new version of a document.
     */
    public function uploadVersion(Request $request, Document $document): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200',
            'change_summary' => 'nullable|string|max:500',
        ]);

        $version = $this->documentVaultService->createVersion(
            $document,
            $request->file('file'),
            $request->user()->id,
            $request->input('change_summary')
        );

        return $this->created($version->load('uploader'));
    }

    /**
     * List versions of a document.
     */
    public function versions(Document $document): JsonResponse
    {
        $versions = $document->versions()
            ->with('uploader')
            ->orderByDesc('version_number')
            ->get();

        return $this->success($versions);
    }

    /**
     * Download a document.
     */
    public function download(Request $request, Document $document): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $downloadInfo = $this->documentVaultService->download($document, $request->user()->id);

        $disk = Storage::disk($downloadInfo['disk']);

        if (!$disk->exists($downloadInfo['path'])) {
            return $this->error('File not found on storage.', 'FILE_NOT_FOUND', 404);
        }

        return $disk->download(
            $downloadInfo['path'],
            $downloadInfo['name'],
            ['Content-Type' => $downloadInfo['mime_type']]
        );
    }

    /**
     * Share a document.
     */
    public function share(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'share_type' => 'nullable|string|in:' . implode(',', DocumentShare::SHARE_TYPES),
            'recipient_email' => 'nullable|email|required_if:share_type,email',
            'access_code' => 'nullable|string|max:32',
            'allow_download' => 'nullable|boolean',
            'max_downloads' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $share = $this->documentVaultService->share($document, $data, $request->user()->id);

        return $this->created($share);
    }

    /**
     * List shares for a document.
     */
    public function shares(Document $document): JsonResponse
    {
        $shares = $document->shares()
            ->with('sharedBy')
            ->orderByDesc('created_at')
            ->get();

        return $this->success($shares);
    }

    /**
     * Revoke a share.
     */
    public function revokeShare(Request $request, Document $document, DocumentShare $share): JsonResponse
    {
        if ($share->document_id !== $document->id) {
            return $this->error('Share does not belong to this document.', 'INVALID_SHARE', 400);
        }

        $share = $this->documentVaultService->revokeShare($share, $request->user()->id);

        return $this->success($share, 'Share revoked successfully.');
    }

    /**
     * Request a digital signature.
     */
    public function sign(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'signer_email' => 'required|email',
            'signer_name' => 'required|string|max:255',
            'signer_id' => 'nullable|integer|exists:users,id',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $signature = $this->documentVaultService->sign($document, $data);

        return $this->created($signature);
    }

    /**
     * List signatures for a document.
     */
    public function signatures(Document $document): JsonResponse
    {
        $signatures = $document->signatures()
            ->with('signer')
            ->orderByDesc('created_at')
            ->get();

        return $this->success($signatures);
    }

    /**
     * Verify a digital signature.
     */
    public function verifySignature(Request $request): JsonResponse
    {
        $data = $request->validate([
            'verification_code' => 'required|string|max:32',
        ]);

        $signature = $this->documentVaultService->verifySignature($data['verification_code']);

        if (!$signature) {
            return $this->error('Invalid verification code.', 'INVALID_CODE', 404);
        }

        return $this->success([
            'signature' => $signature,
            'is_valid' => $signature->isSigned(),
            'document' => $signature->document,
        ]);
    }

    /**
     * Get activity log for a document.
     */
    public function activities(Document $document): JsonResponse
    {
        $activities = $document->activities()
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(50);

        return $this->paginated($activities);
    }

    /**
     * Move document to a folder.
     */
    public function move(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'folder_id' => 'nullable|integer|exists:document_folders,id',
        ]);

        $document = $this->documentVaultService->moveToFolder(
            $document,
            $data['folder_id'] ?? null,
            $request->user()->id
        );

        return $this->success($document, 'Document moved successfully.');
    }
}
