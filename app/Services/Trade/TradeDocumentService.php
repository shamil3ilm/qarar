<?php

declare(strict_types=1);

namespace App\Services\Trade;

use App\Models\Trade\TradeDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TradeDocumentService
{
    /**
     * Create a trade document.
     */
    public function create(array $data): TradeDocument
    {
        return DB::transaction(function () use ($data) {
            return TradeDocument::create($data);
        });
    }

    /**
     * Update a trade document.
     */
    public function update(TradeDocument $document, array $data): ?TradeDocument
    {
        return DB::transaction(function () use ($document, $data) {
            $document->update($data);
            return $document->fresh();
        });
    }

    /**
     * Attach a file to a trade document.
     */
    public function attachFile(TradeDocument $document, string $filePath, string $fileType, int $fileSize): ?TradeDocument
    {
        return DB::transaction(function () use ($document, $filePath, $fileType, $fileSize) {
            $document->update([
                'file_path' => $filePath,
                'file_type' => $fileType,
                'file_size' => $fileSize,
            ]);

            return $document->fresh();
        });
    }

    /**
     * Get documents for a specific entity.
     */
    public function getForEntity(string $sourceType, int $sourceId): Collection
    {
        return TradeDocument::forEntity($sourceType, $sourceId)
            ->orderByDesc('issued_date')
            ->get();
    }

    /**
     * Get documents by type.
     */
    public function getByType(string $documentType, int $perPage = 20)
    {
        return TradeDocument::forType($documentType)
            ->with(['contact:id,name'])
            ->orderByDesc('issued_date')
            ->paginate($perPage);
    }
}
