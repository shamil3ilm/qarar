<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\Document\DigitalSignature;
use App\Models\Document\Document;
use App\Models\Document\DocumentActivity;
use App\Models\Document\DocumentFolder;
use App\Models\Document\DocumentShare;
use App\Models\Document\DocumentVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentVaultService
{
    /**
     * Create a new document folder.
     */
    public function createFolder(array $data, int $userId, int $organizationId): DocumentFolder
    {
        return DB::transaction(function () use ($data, $userId, $organizationId) {
            $data['created_by'] = $data['created_by'] ?? $userId;
            $data['organization_id'] = $data['organization_id'] ?? $organizationId;

            return DocumentFolder::create($data);
        });
    }

    /**
     * Update a folder.
     */
    public function updateFolder(DocumentFolder $folder, array $data): DocumentFolder
    {
        return DB::transaction(function () use ($folder, $data) {
            $folder->update($data);
            return $folder->fresh();
        });
    }

    /**
     * Delete a folder (only non-system folders).
     */
    public function deleteFolder(DocumentFolder $folder): bool
    {
        if ($folder->is_system) {
            throw new \RuntimeException('System folders cannot be deleted.');
        }

        return DB::transaction(function () use ($folder) {
            // Move child documents to parent folder
            Document::where('folder_id', $folder->id)
                ->update(['folder_id' => $folder->parent_id]);

            // Move child folders to parent folder
            DocumentFolder::where('parent_id', $folder->id)
                ->update(['parent_id' => $folder->parent_id]);

            return $folder->delete();
        });
    }

    /**
     * Upload a new document.
     */
    public function upload(UploadedFile $file, array $data, int $userId, int $organizationId): Document
    {
        return DB::transaction(function () use ($file, $data, $userId, $organizationId) {
            $organizationId = $data['organization_id'] ?? $organizationId;

            $extension = $file->getClientOriginalExtension();
            $fileName = Str::uuid() . '.' . $extension;
            $path = "documents/{$organizationId}/{$fileName}";

            Storage::disk(config('filesystems.default', 'local'))->put(
                $path,
                file_get_contents($file->getRealPath())
            );

            $document = Document::create([
                'organization_id' => $organizationId,
                'folder_id' => $data['folder_id'] ?? null,
                'name' => $data['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'extension' => $extension,
                'description' => $data['description'] ?? null,
                'tags' => $data['tags'] ?? null,
                'document_type' => $data['document_type'] ?? null,
                'document_date' => $data['document_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'documentable_type' => $data['documentable_type'] ?? null,
                'documentable_id' => $data['documentable_id'] ?? null,
                'access_level' => $data['access_level'] ?? Document::ACCESS_ORGANIZATION,
                'uploaded_by' => $userId,
            ]);

            // Create initial version
            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'change_summary' => 'Initial upload',
                'uploaded_by' => $userId,
            ]);

            // Log activity
            $this->logActivity($document, DocumentActivity::ACTION_UPLOADED, $userId);

            return $document;
        });
    }

    /**
     * Create a new version of a document.
     */
    public function createVersion(Document $document, UploadedFile $file, int $userId, ?string $changeSummary = null): DocumentVersion
    {
        return DB::transaction(function () use ($document, $file, $userId, $changeSummary) {
            $extension = $file->getClientOriginalExtension();
            $fileName = Str::uuid() . '.' . $extension;
            $path = "documents/{$document->organization_id}/{$fileName}";

            Storage::disk(config('filesystems.default', 'local'))->put(
                $path,
                file_get_contents($file->getRealPath())
            );

            $versionNumber = $document->getNextVersionNumber();

            $version = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $versionNumber,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'change_summary' => $changeSummary,
                'uploaded_by' => $userId,
            ]);

            // Update document with new file info
            $document->update([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $extension,
            ]);

            $this->logActivity($document, DocumentActivity::ACTION_VERSION_CREATED, $userId, [
                'version_number' => $versionNumber,
                'change_summary' => $changeSummary,
            ]);

            return $version;
        });
    }

    /**
     * Download a document (returns the file path and updates counters).
     */
    public function download(Document $document, int $userId): array
    {
        $document->incrementDownloadCount();

        $this->logActivity($document, DocumentActivity::ACTION_DOWNLOADED, $userId);

        return [
            'path' => $document->file_path,
            'name' => $document->file_name,
            'mime_type' => $document->mime_type,
            'disk' => config('filesystems.default', 'local'),
        ];
    }

    /**
     * Share a document externally.
     */
    public function share(Document $document, array $data, int $userId): DocumentShare
    {
        return DB::transaction(function () use ($document, $data, $userId) {
            $share = DocumentShare::create([
                'document_id' => $document->id,
                'shared_by' => $userId,
                'share_type' => $data['share_type'] ?? DocumentShare::TYPE_LINK,
                'recipient_email' => $data['recipient_email'] ?? null,
                'access_code' => $data['access_code'] ?? null,
                'allow_download' => $data['allow_download'] ?? true,
                'max_downloads' => $data['max_downloads'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'is_active' => true,
            ]);

            $this->logActivity($document, DocumentActivity::ACTION_SHARED, $userId, [
                'share_id' => $share->id,
                'share_type' => $share->share_type,
                'recipient_email' => $share->recipient_email,
            ]);

            return $share;
        });
    }

    /**
     * Revoke an existing share.
     */
    public function revokeShare(DocumentShare $share, int $userId): DocumentShare
    {
        return DB::transaction(function () use ($share, $userId) {
            $share->update(['is_active' => false]);

            $this->logActivity($share->document, DocumentActivity::ACTION_SHARED, $userId, [
                'action' => 'revoked',
                'share_id' => $share->id,
            ]);

            return $share->fresh();
        });
    }

    /**
     * Request a digital signature on a document.
     */
    public function sign(Document $document, array $data): DigitalSignature
    {
        return DB::transaction(function () use ($document, $data) {
            return DigitalSignature::create([
                'organization_id' => $document->organization_id,
                'document_id' => $document->id,
                'signer_id' => $data['signer_id'] ?? null,
                'signer_email' => $data['signer_email'],
                'signer_name' => $data['signer_name'],
                'status' => DigitalSignature::STATUS_PENDING,
                'expires_at' => $data['expires_at'] ?? now()->addDays(30),
                'verification_code' => Str::random(32),
            ]);
        });
    }

    /**
     * Verify a digital signature.
     */
    public function verifySignature(string $verificationCode): ?DigitalSignature
    {
        $signature = DigitalSignature::where('verification_code', $verificationCode)
            ->with('document', 'signer')
            ->first();

        if (!$signature) {
            return null;
        }

        return $signature;
    }

    /**
     * Search documents with filters.
     */
    public function search(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Document::query()
            ->with(['folder', 'uploader'])
            ->notArchived();

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['folder_id'])) {
            $query->where('folder_id', $filters['folder_id']);
        }

        if (!empty($filters['document_type'])) {
            $query->ofType($filters['document_type']);
        }

        if (!empty($filters['mime_type'])) {
            $query->where('mime_type', 'like', $filters['mime_type'] . '%');
        }

        if (!empty($filters['tags'])) {
            foreach ((array) $filters['tags'] as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if (!empty($filters['documentable_type'])) {
            $query->where('documentable_type', $filters['documentable_type']);
        }

        if (!empty($filters['documentable_id'])) {
            $query->where('documentable_id', $filters['documentable_id']);
        }

        if (isset($filters['is_archived'])) {
            $filters['is_archived'] ? $query->archived() : $query->notArchived();
        }

        if (!empty($filters['expiring_within_days'])) {
            $query->expiringSoon((int) $filters['expiring_within_days']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Move a document to a different folder.
     */
    public function moveToFolder(Document $document, ?int $folderId, int $userId): Document
    {
        return DB::transaction(function () use ($document, $folderId, $userId) {
            $oldFolderId = $document->folder_id;
            $document->update(['folder_id' => $folderId]);

            $this->logActivity($document, DocumentActivity::ACTION_MOVED, $userId, [
                'from_folder_id' => $oldFolderId,
                'to_folder_id' => $folderId,
            ]);

            return $document->fresh();
        });
    }

    /**
     * Update a document's metadata.
     */
    public function updateDocument(Document $document, array $data, int $userId): Document
    {
        return DB::transaction(function () use ($document, $data, $userId) {
            $document->update($data);

            $this->logActivity($document, DocumentActivity::ACTION_EDITED, $userId);

            return $document->fresh();
        });
    }

    /**
     * Delete (soft) a document.
     */
    public function deleteDocument(Document $document, int $userId): bool
    {
        return DB::transaction(function () use ($document, $userId) {
            $this->logActivity($document, DocumentActivity::ACTION_DELETED, $userId);
            return $document->delete();
        });
    }

    /**
     * Log a document activity.
     */
    protected function logActivity(Document $document, string $action, int $userId, ?array $metadata = null): DocumentActivity
    {
        return DocumentActivity::create([
            'document_id' => $document->id,
            'user_id' => $userId,
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
        ]);
    }
}
