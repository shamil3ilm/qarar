<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCertification;
use App\Models\Inventory\ProductDocument;
use App\Models\Inventory\ProductImage;
use App\Models\Inventory\ProductPriceHistory;
use App\Models\Inventory\ProductRelation;
use App\Models\Inventory\ProductReview;
use App\Models\Inventory\ProductSpecification;
use App\Models\Inventory\ProductVideo;
use Illuminate\Support\Facades\DB;

class ProductDetailService
{
    public function getProductDetails(int $productId): array
    {
        $product = Product::with([
            'specifications', 'images', 'documents', 'videos',
            'certifications', 'reviews' => fn ($q) => $q->where('status', 'approved'),
        ])->findOrFail($productId);

        return [
            'product' => $product->toArray(),
            'average_rating' => $this->getAverageRating($productId),
        ];
    }

    public function syncSpecifications(int $productId, array $specs): void
    {
        DB::transaction(function () use ($productId, $specs) {
            ProductSpecification::where('product_id', $productId)->delete();

            foreach ($specs as $index => $spec) {
                ProductSpecification::create([
                    'product_id' => $productId,
                    'spec_group' => $spec['spec_group'] ?? null,
                    'spec_name' => $spec['spec_name'],
                    'spec_value' => $spec['spec_value'],
                    'unit' => $spec['unit'] ?? null,
                    'display_order' => $index,
                ]);
            }
        });
    }

    public function addImage(int $productId, array $data): ProductImage
    {
        $data['product_id'] = $productId;

        if (!empty($data['is_primary'])) {
            ProductImage::where('product_id', $productId)->update(['is_primary' => false]);
        }

        return ProductImage::create($data);
    }

    public function removeImage(int $imageId): void
    {
        ProductImage::findOrFail($imageId)->delete();
    }

    public function reorderImages(int $productId, array $order): void
    {
        foreach ($order as $index => $imageId) {
            ProductImage::where('id', $imageId)
                ->where('product_id', $productId)
                ->update(['display_order' => $index]);
        }
    }

    public function addDocument(int $productId, array $data): ProductDocument
    {
        $data['product_id'] = $productId;
        return ProductDocument::create($data);
    }

    public function addVideo(int $productId, array $data): ProductVideo
    {
        $data['product_id'] = $productId;
        return ProductVideo::create($data);
    }

    public function setRelations(int $productId, string $relationType, array $relatedIds): void
    {
        DB::transaction(function () use ($productId, $relationType, $relatedIds) {
            ProductRelation::where('product_id', $productId)
                ->where('relation_type', $relationType)
                ->delete();

            foreach ($relatedIds as $index => $relatedId) {
                ProductRelation::create([
                    'product_id' => $productId,
                    'related_product_id' => $relatedId,
                    'relation_type' => $relationType,
                    'display_order' => $index,
                ]);
            }
        });
    }

    public function submitReview(int $productId, array $data): ProductReview
    {
        $data['product_id'] = $productId;
        $data['status'] = 'pending';
        return ProductReview::create($data);
    }

    public function approveReview(int $reviewId, int $userId): ProductReview
    {
        $review = ProductReview::findOrFail($reviewId);
        $review->update(['status' => 'approved', 'approved_by' => $userId, 'approved_at' => now()]);
        return $review->fresh();
    }

    public function getAverageRating(int $productId): float
    {
        return (float) ProductReview::where('product_id', $productId)
            ->where('status', 'approved')
            ->avg('rating') ?? 0;
    }

    public function recordPriceChange(int $productId, string $priceType, float $oldPrice, float $newPrice, ?string $reason, int $userId): ProductPriceHistory
    {
        $changePercent = $oldPrice > 0 ? (($newPrice - $oldPrice) / $oldPrice) * 100 : 0;

        return ProductPriceHistory::create([
            'product_id' => $productId,
            'price_type' => $priceType,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'change_percent' => round($changePercent, 2),
            'currency_code' => 'SAR',
            'reason' => $reason,
            'effective_from' => now(),
            'changed_by' => $userId,
        ]);
    }

    public function addCertification(int $productId, array $data): ProductCertification
    {
        $data['product_id'] = $productId;
        return ProductCertification::create($data);
    }
}
