<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    /**
     * List expense categories (tree structure).
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExpenseCategory::with(['children', 'defaultAccount:id,code,name'])
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        if ($request->boolean('flat', false)) {
            // Flat list for dropdowns
            $categories = $query->orderBy('name')->get();
            return $this->success($categories);
        }

        // Tree structure - only root categories
        $query->whereNull('parent_id');

        $categories = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return $this->paginated($categories);
    }

    /**
     * Create a new expense category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'exists:expense_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('expense_categories', 'code')->where('organization_id', auth()->user()->organization_id)],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'description' => ['nullable', 'string'],
            'default_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'requires_receipt' => ['nullable', 'boolean'],
            'budget_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $category = ExpenseCategory::create($validated);

        return $this->created(
            $category->load(['parent', 'defaultAccount:id,code,name']),
            'Expense category created successfully'
        );
    }

    /**
     * Show a single expense category.
     */
    public function show(ExpenseCategory $expenseCategory): JsonResponse
    {
        $expenseCategory->load(['parent', 'children', 'defaultAccount:id,code,name']);

        return $this->success($expenseCategory);
    }

    /**
     * Update an expense category.
     */
    public function update(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'exists:expense_categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'description' => ['nullable', 'string'],
            'default_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'requires_receipt' => ['nullable', 'boolean'],
            'budget_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Prevent setting parent to itself or its own children
        if (isset($validated['parent_id']) && $validated['parent_id'] == $expenseCategory->id) {
            return $this->error('A category cannot be its own parent', 'VALIDATION_ERROR', 422);
        }

        $expenseCategory->update($validated);

        return $this->success(
            $expenseCategory->fresh(['parent', 'children', 'defaultAccount:id,code,name']),
            'Expense category updated successfully'
        );
    }

    /**
     * Delete an expense category.
     */
    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        // Check if category has expenses
        if ($expenseCategory->expenses()->exists()) {
            return $this->error('Cannot delete category with existing expenses', 'HAS_DEPENDENCIES', 400);
        }

        // Check if category has children
        if ($expenseCategory->children()->exists()) {
            return $this->error('Cannot delete category with sub-categories', 'HAS_DEPENDENCIES', 400);
        }

        $expenseCategory->delete();

        return $this->success(null, 'Expense category deleted successfully');
    }
}
