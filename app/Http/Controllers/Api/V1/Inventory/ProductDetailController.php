<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCertification;
use App\Models\Inventory\ProductDocument;
use App\Models\Inventory\ProductImage;
use App\Models\Inventory\ProductReview;
use App\Models\Inventory\ProductVideo;
use App\Services\Inventory\ProductDetailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductDetailController extends Controller
{
    public function __construct(private ProductDetailService $service) {}

    public function show(Product $product): JsonResponse
    {
        return $this->success($this->service->getProductDetails($product->id));
    }

    public function specifications(Product $product): JsonResponse
    {
        return $this->success($product->specifications()->orderBy('display_order')->get());
    }

    public function syncSpecifications(Request $request, Product $product): JsonResponse
    {
        $this->service->syncSpecifications($product->id, $request->input('specifications', []));
        return $this->success($product->specifications()->orderBy('display_order')->get());
    }

    public function images(Product $product): JsonResponse
    {
        return $this->success($product->images()->orderBy('display_order')->get());
    }

    public function addImage(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image_path' => 'required|string|max:500',
            'alt_text' => 'nullable|string|max:255',
            'is_primary' => 'nullable|boolean',
            'display_order' => 'nullable|integer',
        ]);

        return $this->created($this->service->addImage($product->id, $request->all()));
    }

    public function removeImage(Product $product, ProductImage $image): JsonResponse
    {
        $this->service->removeImage($image->id);
        return $this->success(['message' => 'Image removed']);
    }

    public function reorderImages(Request $request, Product $product): JsonResponse
    {
        $this->service->reorderImages($product->id, $request->input('order', []));
        return $this->success(['message' => 'Images reordered']);
    }

    public function documents(Product $product): JsonResponse
    {
        return $this->success($product->documents()->orderBy('display_order')->get());
    }

    public function addDocument(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'file_path' => 'required|string|max:500',
            'document_type' => 'nullable|string|max:50',
            'display_order' => 'nullable|integer',
        ]);

        return $this->created($this->service->addDocument($product->id, $request->all()));
    }

    public function removeDocument(Product $product, ProductDocument $document): JsonResponse
    {
        $document->delete();
        return $this->success(['message' => 'Document removed']);
    }

    public function videos(Product $product): JsonResponse
    {
        return $this->success($product->videos()->orderBy('display_order')->get());
    }

    public function addVideo(Request $request, Product $product): JsonResponse
    {
        return $this->created($this->service->addVideo($product->id, $request->all()));
    }

    public function removeVideo(Product $product, ProductVideo $video): JsonResponse
    {
        $video->delete();
        return $this->success(['message' => 'Video removed']);
    }

    public function relations(Product $product): JsonResponse
    {
        return $this->success($product->relations()->with('relatedProduct')->get());
    }

    public function setRelations(Request $request, Product $product): JsonResponse
    {
        $this->service->setRelations($product->id, $request->input('relation_type'), $request->input('related_ids', []));
        return $this->success(['message' => 'Relations updated']);
    }

    public function reviews(Request $request, Product $product): JsonResponse
    {
        $reviews = ProductReview::where('product_id', $product->id)
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->get();
        return $this->success($reviews);
    }

    public function submitReview(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'review_text' => 'nullable|string',
            'reviewer_name' => 'nullable|string|max:255',
        ]);

        return $this->created($this->service->submitReview($product->id, $request->all()));
    }

    public function approveReview(Product $product, ProductReview $review): JsonResponse
    {
        return $this->success($this->service->approveReview($review->id, auth()->id()));
    }

    public function rejectReview(Product $product, ProductReview $review): JsonResponse
    {
        $review->update(['status' => 'rejected']);
        return $this->success($review->fresh());
    }

    public function priceHistory(Product $product): JsonResponse
    {
        return $this->success($product->priceHistory()->orderByDesc('effective_from')->get());
    }

    public function certifications(Product $product): JsonResponse
    {
        return $this->success($product->certifications);
    }

    public function addCertification(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'certification_name' => 'required|string|max:255',
            'issuing_body' => 'nullable|string|max:255',
            'certificate_number' => 'nullable|string|max:100',
            'issued_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
        ]);

        return $this->created($this->service->addCertification($product->id, $request->all()));
    }

    public function updateCertification(Request $request, Product $product, ProductCertification $certification): JsonResponse
    {
        $certification->update($request->all());
        return $this->success($certification->fresh());
    }
}
