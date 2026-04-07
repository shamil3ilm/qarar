<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\CategoryResource;
use App\Models\Inventory\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * List categories as tree or flat.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::query()
            ->when($request->boolean('tree'), fn($q) => $q->whereNull('parent_id')->with('allChildren'))
            ->when($request->boolean('active_only'), fn($q) => $q->active());

        $categories = $query->get();

        return $this->success(CategoryResource::collection($categories));
    }

    /**
     * Create a new category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:categories,id',
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:categories,slug',
            'description' => 'nullable|string|max:500',
            'image_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = \Str::slug($validated['name']);
        }

        $category = Category::create($validated);

        return $this->created(new CategoryResource($category), 'Category created successfully.');
    }

    /**
     * Show a category.
     */
    public function show(Category $category): JsonResponse
    {
        $category->load(['parent', 'children', 'products']);

        return $this->success(new CategoryResource($category));
    }

    /**
     * Update a category.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:categories,id',
            'name' => 'sometimes|required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:categories,slug,' . $category->id,
            'description' => 'nullable|string|max:500',
            'image_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        // Prevent setting parent to self or descendant
        if (isset($validated['parent_id'])) {
            if ($validated['parent_id'] === $category->id) {
                return $this->error('Category cannot be its own parent.', 'VALIDATION_ERROR', 422);
            }

            $candidateParent = Category::find($validated['parent_id']);
            if ($candidateParent && $category->isAncestorOf($candidateParent)) {
                return $this->error('Cannot set a descendant as parent.', 'VALIDATION_ERROR', 422);
            }
        }

        $category->update($validated);

        return $this->success(new CategoryResource($category->fresh()), 'Category updated successfully.');
    }

    /**
     * Delete a category.
     */
    public function destroy(Category $category): JsonResponse
    {
        // Check for products
        if ($category->products()->count() > 0) {
            return $this->error('Cannot delete category with products. Move products first.', 'VALIDATION_ERROR', 422);
        }

        // Check for children
        if ($category->children()->count() > 0) {
            return $this->error('Cannot delete category with subcategories.', 'VALIDATION_ERROR', 422);
        }

        $category->delete();

        return $this->success(null, 'Category deleted successfully.');
    }

    /**
     * Move a category to a new parent.
     */
    public function move(Request $request, Category $category): JsonResponse
    {
        $request->validate([
            'parent_id' => 'nullable|integer|exists:categories,id',
        ]);

        $newParentId = $request->input('parent_id');

        if ($newParentId === $category->id) {
            return $this->error('Category cannot be its own parent.', 'VALIDATION_ERROR', 422);
        }

        $newParent = $newParentId ? Category::find($newParentId) : null;
        if ($newParent && $category->isAncestorOf($newParent)) {
            return $this->error('Cannot move category under its own descendant.', 'VALIDATION_ERROR', 422);
        }

        $category->update(['parent_id' => $newParentId]);

        return $this->success(new CategoryResource($category->fresh(['parent'])), 'Category moved successfully.');
    }
}
