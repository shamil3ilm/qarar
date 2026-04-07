<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\DashboardLayout;
use App\Models\Core\DashboardWidget;
use App\Models\Core\OrganizationSubscription;
use App\Services\Core\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Get dashboard data.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->get('type', DashboardLayout::TYPE_MAIN);

        $layout = DashboardLayout::getForUser(
            $user->organization_id,
            $user->id,
            $type
        );

        $this->dashboardService->setContext($user->organization_id, $user->current_branch_id);

        $data = $this->dashboardService->getDashboardData($layout);

        return $this->success($data, 'Dashboard data retrieved successfully');
    }

    /**
     * Get quick stats overview from all modules.
     */
    public function quickStats(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->dashboardService->setContext($user->organization_id, $user->current_branch_id);

        return $this->success([
            'stats' => $this->dashboardService->getQuickStats(),
            'generated_at' => now()->toIso8601String(),
        ], 'Quick stats retrieved successfully');
    }

    /**
     * Get single widget data.
     */
    public function widget(Request $request, string $widgetCode): JsonResponse
    {
        $user = $request->user();
        $widget = DashboardWidget::getByCode($widgetCode);

        if (!$widget) {
            return $this->notFound('Widget not found');
        }

        // Check premium access
        if ($widget->is_premium) {
            $subscription = OrganizationSubscription::getCurrentForOrganization($user->organization_id);
            if (!$subscription?->hasFeature('dashboard_customization')) {
                return $this->forbidden('Premium feature');
            }
        }

        $this->dashboardService->setContext($user->organization_id, $user->current_branch_id);

        $config = array_merge($widget->default_config ?? [], $request->all());
        $data = $this->dashboardService->getWidgetData($widget, $config);

        return $this->success([
            'widget' => $widget->toArray(),
            'widget_data' => $data,
        ], 'Widget data retrieved successfully');
    }

    /**
     * Get available widgets.
     */
    public function widgets(Request $request): JsonResponse
    {
        $user = $request->user();
        $module = $request->get('module');
        $category = $request->get('category');

        $subscription = OrganizationSubscription::getCurrentForOrganization($user->organization_id);
        $includePremium = $subscription?->hasFeature('dashboard_customization') ?? false;

        $query = DashboardWidget::active()->orderBy('sort_order');

        if ($module) {
            $query->forModule($module);
        }

        if ($category) {
            $query->forCategory($category);
        }

        if (!$includePremium) {
            $query->free();
        }

        $widgets = $query->get();

        return $this->success([
            'widgets' => $widgets,
            'categories' => DashboardWidget::getCategories(),
            'types' => DashboardWidget::getTypes(),
            'premium_access' => $includePremium,
        ], 'Widgets retrieved successfully');
    }

    /**
     * Get user's dashboard layouts.
     */
    public function layouts(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get user's own layouts
        $userLayouts = DashboardLayout::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->get();

        // Get shared organization layouts
        $sharedLayouts = DashboardLayout::where('organization_id', $user->organization_id)
            ->whereNull('user_id')
            ->where('is_shared', true)
            ->get();

        return $this->success([
            'user_layouts' => $userLayouts,
            'shared_layouts' => $sharedLayouts,
            'types' => DashboardLayout::getTypes(),
        ], 'Layouts retrieved successfully');
    }

    /**
     * Get a specific layout.
     */
    public function layout(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $layout = DashboardLayout::where('organization_id', $user->organization_id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('is_shared', true);
            })
            ->findOrFail($id);

        $this->dashboardService->setContext($user->organization_id, $user->current_branch_id);
        $data = $this->dashboardService->getDashboardData($layout);

        return $this->success($data, 'Layout retrieved successfully');
    }

    /**
     * Create a new layout.
     */
    public function createLayout(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:' . implode(',', array_keys(DashboardLayout::getTypes())),
            'widgets' => 'nullable|array',
            'layout' => 'nullable|array',
            'is_default' => 'nullable|boolean',
            'is_shared' => 'nullable|boolean',
        ]);

        $user = $request->user();

        // Check if user can create shared layouts
        $isShared = $request->boolean('is_shared');
        if ($isShared && !$user->hasPermission('core.settings.edit')) {
            return $this->forbidden('Permission denied for shared layouts');
        }

        $layout = DashboardLayout::create([
            'organization_id' => $user->organization_id,
            'user_id' => $isShared ? null : $user->id,
            'name' => $request->get('name'),
            'type' => $request->get('type'),
            'widgets' => $request->get('widgets', []),
            'layout' => $request->get('layout', ['columns' => 4, 'row_height' => 150, 'gap' => 16]),
            'is_default' => $request->boolean('is_default'),
            'is_shared' => $isShared,
        ]);

        // If setting as default, unset other defaults
        if ($layout->is_default) {
            DashboardLayout::where('organization_id', $user->organization_id)
                ->where('user_id', $isShared ? null : $user->id)
                ->where('type', $layout->type)
                ->where('id', '!=', $layout->id)
                ->update(['is_default' => false]);
        }

        return $this->success($layout, 'Layout created successfully');
    }

    /**
     * Update a layout.
     */
    public function updateLayout(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $layout = DashboardLayout::where('organization_id', $user->organization_id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere(function ($q2) use ($user) {
                        $q2->whereNull('user_id')
                            ->where('is_shared', true);
                    });
            })
            ->findOrFail($id);

        // Check permission for shared layouts
        if ($layout->is_shared && !$user->hasPermission('core.settings.edit')) {
            return $this->forbidden('Permission denied');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'widgets' => 'sometimes|array',
            'layout' => 'sometimes|array',
            'is_default' => 'sometimes|boolean',
        ]);

        $layout->fill($validated);
        $layout->save();

        // If setting as default, unset other defaults
        if ($request->boolean('is_default')) {
            DashboardLayout::where('organization_id', $user->organization_id)
                ->where('user_id', $layout->user_id)
                ->where('type', $layout->type)
                ->where('id', '!=', $layout->id)
                ->update(['is_default' => false]);
        }

        return $this->success($layout, 'Layout updated successfully');
    }

    /**
     * Delete a layout.
     */
    public function deleteLayout(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $layout = DashboardLayout::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $layout->delete();

        return $this->success(null, 'Layout deleted');
    }

    /**
     * Add widget to layout.
     */
    public function addWidget(Request $request, int $layoutId): JsonResponse
    {
        $user = $request->user();

        $layout = DashboardLayout::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->findOrFail($layoutId);

        $request->validate([
            'widget_code' => 'required|string|exists:dashboard_widgets,code',
            'size' => 'required|string',
            'position' => 'required|array',
            'position.x' => 'required|integer|min:0',
            'position.y' => 'required|integer|min:0',
            'config' => 'nullable|array',
        ]);

        $layout->addWidget(
            $request->get('widget_code'),
            $request->get('size'),
            $request->get('position'),
            $request->get('config', [])
        );

        return $this->success($layout, 'Widget added successfully');
    }

    /**
     * Remove widget from layout.
     */
    public function removeWidget(Request $request, int $layoutId, string $widgetCode): JsonResponse
    {
        $user = $request->user();

        $layout = DashboardLayout::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->findOrFail($layoutId);

        $layout->removeWidget($widgetCode);

        return $this->success($layout, 'Widget removed successfully');
    }

    /**
     * Update widget position.
     */
    public function updateWidgetPosition(Request $request, int $layoutId, string $widgetCode): JsonResponse
    {
        $user = $request->user();

        $layout = DashboardLayout::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->findOrFail($layoutId);

        $request->validate([
            'position' => 'required|array',
            'position.x' => 'required|integer|min:0',
            'position.y' => 'required|integer|min:0',
        ]);

        $layout->updateWidgetPosition($widgetCode, $request->get('position'));

        return $this->success($layout, 'Widget position updated successfully');
    }

    /**
     * Reset layout to default.
     */
    public function resetLayout(Request $request, string $type): JsonResponse
    {
        $user = $request->user();

        // Delete existing user layout for this type
        DashboardLayout::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->delete();

        // Create new default layout
        $layout = DashboardLayout::createDefaultLayout(
            $user->organization_id,
            $user->id,
            $type
        );

        $this->dashboardService->setContext($user->organization_id, $user->current_branch_id);
        $data = $this->dashboardService->getDashboardData($layout);

        return $this->success($data, 'Layout reset to default');
    }
}
