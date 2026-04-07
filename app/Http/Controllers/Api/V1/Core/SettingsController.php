<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\FeatureFlag;
use App\Models\Core\NumberSequence;
use App\Models\Core\UserPreference;
use App\Models\System\Setting;
use App\Services\Core\RegionalDefaultsService;
use App\Services\Core\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settingsService
    ) {}

    // ==========================================
    // Organization Settings
    // ==========================================

    /**
     * Get all organization settings.
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $settings = $this->settingsService->getAll($organizationId);

        return $this->success($settings, 'Settings retrieved successfully');
    }

    /**
     * Get settings by group.
     */
    public function getGroup(Request $request, string $group): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $settings = Setting::getGroup($group, $organizationId);

        return $this->success([
            'group' => $group,
            'settings' => $settings,
        ], 'Settings group retrieved successfully');
    }

    /**
     * Get a single setting value.
     */
    public function show(Request $request, string $key): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $value = $this->settingsService->get($key, $organizationId);
        $definition = $this->settingsService->getDefinition($key);

        return $this->success([
            'key' => $key,
            'value' => $value,
            'definition' => $definition,
        ], 'Setting retrieved successfully');
    }

    /**
     * Update a single setting.
     */
    public function update(Request $request, string $key): JsonResponse
    {


        $request->validate([
            'value' => 'present',
        ]);

        $organizationId = auth()->user()->organization_id;

        try {
            $this->settingsService->set($key, $request->input('value'), $organizationId);

            return $this->success([
                'key' => $key,
                'value' => $this->settingsService->get($key, $organizationId),
            ], 'Setting updated successfully');
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['value' => $e->getMessage()]);
        }
    }

    /**
     * Update multiple settings at once.
     */
    public function updateMany(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        $organizationId = auth()->user()->organization_id;
        $errors = [];

        foreach ($request->input('settings') as $key => $value) {
            try {
                $this->settingsService->set($key, $value, $organizationId);
            } catch (\InvalidArgumentException $e) {
                $errors[$key] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        return $this->success(null, 'Settings updated successfully');
    }

    /**
     * Update settings for a group.
     */
    public function updateGroup(Request $request, string $group): JsonResponse
    {


        $request->validate([
            'settings' => 'required|array',
        ]);

        $organizationId = auth()->user()->organization_id;

        $errors = [];
        foreach ($request->input('settings') as $key => $value) {
            try {
                $this->settingsService->set("{$group}.{$key}", $value, $organizationId);
            } catch (\InvalidArgumentException $e) {
                $errors[$key] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        return $this->success([
            'group' => $group,
            'settings' => Setting::getGroup($group, $organizationId),
        ], 'Group settings updated successfully');
    }

    /**
     * Reset a setting to its default value.
     */
    public function reset(Request $request, string $key): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $this->settingsService->delete($key, $organizationId);

        return $this->success([
            'key' => $key,
            'value' => $this->settingsService->get($key, $organizationId),
        ], 'Setting reset to default');
    }

    /**
     * Get available setting definitions.
     */
    public function getDefinitions(): JsonResponse
    {
        return $this->success(
            $this->settingsService->getDefinitions(),
            'Setting definitions retrieved successfully'
        );
    }

    // ==========================================
    // User Preferences
    // ==========================================

    /**
     * Get all user preferences.
     */
    public function getUserPreferences(): JsonResponse
    {
        $userId = auth()->id();

        return $this->success(
            UserPreference::getAllForUser($userId),
            'User preferences retrieved successfully'
        );
    }

    /**
     * Get a single user preference.
     */
    public function getUserPreference(string $key): JsonResponse
    {
        $userId = auth()->id();
        $value = UserPreference::getValue($userId, $key);

        return $this->success([
            'key' => $key,
            'value' => $value,
        ], 'Preference retrieved successfully');
    }

    /**
     * Set a user preference.
     */
    public function setUserPreference(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'value' => 'present',
        ]);

        $userId = auth()->id();
        UserPreference::setValue($userId, $key, $request->input('value'));

        return $this->success([
            'key' => $key,
            'value' => $request->input('value'),
        ], 'Preference saved');
    }

    /**
     * Update multiple user preferences.
     */
    public function setUserPreferences(Request $request): JsonResponse
    {
        $request->validate([
            'preferences' => 'required|array',
        ]);

        $userId = auth()->id();

        foreach ($request->input('preferences') as $key => $value) {
            UserPreference::setValue($userId, $key, $value);
        }

        return $this->success(UserPreference::getAllForUser($userId), 'Preferences saved');
    }

    /**
     * Delete a user preference.
     */
    public function deleteUserPreference(string $key): JsonResponse
    {
        $userId = auth()->id();
        UserPreference::deleteValue($userId, $key);

        return $this->success(null, 'Preference deleted');
    }

    // ==========================================
    // Feature Flags
    // ==========================================

    /**
     * Get all feature flags for the organization.
     */
    public function getFeatures(): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        return $this->success(FeatureFlag::getAllForOrganization($organizationId));
    }

    /**
     * Check if a feature is enabled.
     */
    public function checkFeature(string $feature): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        return $this->success([
            'feature' => $feature,
            'enabled' => FeatureFlag::isEnabled($organizationId, $feature),
            'config' => FeatureFlag::getConfig($organizationId, $feature),
        ]);
    }

    /**
     * Enable a feature.
     */
    public function enableFeature(Request $request, string $feature): JsonResponse
    {


        $organizationId = auth()->user()->organization_id;
        $config = $request->input('config');

        FeatureFlag::enableFeature($organizationId, $feature, $config);

        return $this->success([
            'feature' => $feature,
            'enabled' => true,
        ], 'Feature enabled');
    }

    /**
     * Disable a feature.
     */
    public function disableFeature(string $feature): JsonResponse
    {


        $organizationId = auth()->user()->organization_id;

        FeatureFlag::disableFeature($organizationId, $feature);

        return $this->success([
            'feature' => $feature,
            'enabled' => false,
        ], 'Feature disabled');
    }

    /**
     * Get available features list.
     */
    public function getAvailableFeatures(): JsonResponse
    {
        return $this->success(FeatureFlag::getAvailableFeatures());
    }

    // ==========================================
    // Number Sequences
    // ==========================================

    /**
     * Get all number sequences.
     */
    public function getNumberSequences(): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $sequences = NumberSequence::where('organization_id', $organizationId)
            ->orderBy('type')
            ->get()
            ->map(fn($seq) => [
                'id' => $seq->id,
                'type' => $seq->type,
                'branch_id' => $seq->branch_id,
                'prefix' => $seq->prefix,
                'suffix' => $seq->suffix,
                'current_number' => $seq->current_number,
                'padding' => $seq->padding,
                'include_year' => $seq->include_year,
                'include_month' => $seq->include_month,
                'reset_yearly' => $seq->reset_yearly,
                'reset_monthly' => $seq->reset_monthly,
                'next_number' => $seq->getFormattedNumber(),
            ]);

        return $this->success($sequences);
    }

    /**
     * Get a specific number sequence.
     */
    public function getNumberSequence(string $type, Request $request): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;
        $branchId = $request->input('branch_id');

        $sequence = NumberSequence::where('organization_id', $organizationId)
            ->where('type', $type)
            ->where('branch_id', $branchId)
            ->first();

        if (!$sequence) {
            // Return default configuration
            $default = NumberSequence::DEFAULT_CONFIGS[$type] ?? ['prefix' => strtoupper($type) . '-', 'padding' => 5];
            return $this->success([
                'type' => $type,
                'prefix' => $default['prefix'] ?? null,
                'suffix' => $default['suffix'] ?? null,
                'current_number' => 0,
                'padding' => $default['padding'] ?? 5,
                'include_year' => $default['include_year'] ?? true,
                'include_month' => $default['include_month'] ?? false,
                'reset_yearly' => $default['reset_yearly'] ?? true,
                'reset_monthly' => $default['reset_monthly'] ?? false,
                'is_default' => true,
            ]);
        }

        return $this->success([
            'id' => $sequence->id,
            'type' => $sequence->type,
            'branch_id' => $sequence->branch_id,
            'prefix' => $sequence->prefix,
            'suffix' => $sequence->suffix,
            'current_number' => $sequence->current_number,
            'padding' => $sequence->padding,
            'include_year' => $sequence->include_year,
            'include_month' => $sequence->include_month,
            'reset_yearly' => $sequence->reset_yearly,
            'reset_monthly' => $sequence->reset_monthly,
            'next_number' => NumberSequence::peekNext($organizationId, $type, $branchId),
            'is_default' => false,
        ]);
    }

    /**
     * Update a number sequence configuration.
     */
    public function updateNumberSequence(Request $request, string $type): JsonResponse
    {


        $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'prefix' => 'nullable|string|max:20',
            'suffix' => 'nullable|string|max:20',
            'padding' => 'integer|min:1|max:10',
            'include_year' => 'boolean',
            'include_month' => 'boolean',
            'reset_yearly' => 'boolean',
            'reset_monthly' => 'boolean',
            'current_number' => 'nullable|integer|min:0',
        ]);

        $organizationId = auth()->user()->organization_id;
        $branchId = $request->input('branch_id');

        $sequence = NumberSequence::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'type' => $type,
                'branch_id' => $branchId,
            ],
            array_filter([
                'prefix' => $request->input('prefix'),
                'suffix' => $request->input('suffix'),
                'padding' => $request->input('padding', 5),
                'include_year' => $request->input('include_year', true),
                'include_month' => $request->input('include_month', false),
                'reset_yearly' => $request->input('reset_yearly', true),
                'reset_monthly' => $request->input('reset_monthly', false),
                'current_number' => $request->input('current_number'),
                'last_reset_year' => now()->year,
                'last_reset_month' => now()->month,
            ], fn($v) => $v !== null)
        );

        return $this->success([
            'id' => $sequence->id,
            'type' => $sequence->type,
            'next_number' => NumberSequence::peekNext($organizationId, $type, $branchId),
        ], 'Number sequence updated');
    }

    /**
     * Preview next number in sequence.
     */
    public function previewNextNumber(string $type, Request $request): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;
        $branchId = $request->input('branch_id');

        return $this->success([
            'type' => $type,
            'next_number' => NumberSequence::peekNext($organizationId, $type, $branchId),
        ]);
    }

    /**
     * Get available sequence types.
     */
    public function getSequenceTypes(): JsonResponse
    {
        return $this->success(NumberSequence::DEFAULT_CONFIGS);
    }

    // ==========================================
    // Cache Management
    // ==========================================

    /**
     * Clear all settings cache for the organization.
     */
    public function clearCache(): JsonResponse
    {


        $organizationId = auth()->user()->organization_id;

        $this->settingsService->clearAllCache($organizationId);

        // Clear feature flags cache
        $features = FeatureFlag::where('organization_id', $organizationId)
            ->pluck('feature');
        foreach ($features as $feature) {
            Cache::forget("feature_flag:{$organizationId}:{$feature}");
        }

        return $this->success(null, 'Settings cache cleared');
    }

    // ==========================================
    // Regional Settings
    // ==========================================

    /**
     * GET /settings/regions
     * Returns all supported countries with their region labels and currency.
     */
    public function regions(): JsonResponse
    {
        $countries = app(RegionalDefaultsService::class)->getSupportedCountries();

        return $this->success($countries, 'Supported regions retrieved.');
    }

    /**
     * GET /settings/regions/{countryCode}/preview
     * Returns what defaults would be applied for a country without saving.
     */
    public function previewRegionDefaults(string $countryCode): JsonResponse
    {
        $service  = app(RegionalDefaultsService::class);
        $defaults = $service->getDefaultsForCountry(strtoupper($countryCode));

        return $this->success([
            'country_code' => strtoupper($countryCode),
            'region'       => $service->getRegionLabel($countryCode),
            'defaults'     => $defaults,
        ], 'Regional defaults preview.');
    }

    /**
     * POST /settings/initialize-region
     * Apply regional defaults for the current organization.
     *
     * Body: { country_code: "SA", force: false }
     * When force=false (default): only fills keys that have no existing value.
     * When force=true: overwrites all settings with regional defaults.
     */
    public function initializeRegion(Request $request): JsonResponse
    {


        $validated = $request->validate([
            'country_code' => ['required', 'string', 'size:2'],
            'force'        => ['sometimes', 'boolean'],
        ]);

        $orgId  = $this->organizationId($request);
        if (!$orgId) {
            return $this->error('Organization not found.', 'ORGANIZATION_NOT_FOUND', 422);
        }
        $result = $this->settingsService->initializeByCountry(
            organizationId: $orgId,
            countryCode:    strtoupper($validated['country_code']),
            force:          (bool) ($validated['force'] ?? false),
        );

        return $this->success($result, 'Regional defaults applied.');
    }

    /**
     * POST /settings/bulk-reset-to-region
     * Hard-reset ALL settings to the org's current country_code defaults.
     * Equivalent to initializeRegion with force=true, using the org's stored country.
     */
    public function resetToRegion(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        if (!$orgId) {
            return $this->error('Organization not found.', 'ORGANIZATION_NOT_FOUND', 422);
        }
        $org   = \App\Models\Core\Organization::findOrFail($orgId);

        if (empty($org->country_code)) {
            return $this->error('Organization has no country_code set.', 'MISSING_COUNTRY_CODE', 422);
        }

        $result = $this->settingsService->initializeByCountry(
            organizationId: $orgId,
            countryCode:    $org->country_code,
            force:          true,
        );

        return $this->success($result, 'All settings reset to regional defaults.');
    }
}
