<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\Language;
use App\Models\Core\OrganizationBranding;
use App\Models\Core\Translation;
use App\Services\Core\LocalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LocalizationController extends Controller
{
    public function __construct(
        protected LocalizationService $localizationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->localizationService->setOrganization($request->user()->organization_id);
        $this->localizationService->setLanguage($request->user()->preferred_language ?? 'en');

        return $this->success($this->localizationService->getFrontendData());
    }

    public function languages(): JsonResponse
    {
        return $this->success([
            'languages' => Language::active()->ordered()->get(),
            'default'   => Language::getDefault(),
        ]);
    }

    public function translations(Request $request, string $languageCode): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        return $this->success([
            'translations' => Translation::getAllForLanguage($languageCode, $organizationId),
            'language'     => $languageCode,
            'direction'    => $this->localizationService->getDirection($languageCode),
        ]);
    }

    public function translationGroup(Request $request, string $languageCode, string $group): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        return $this->success([
            'translations' => Translation::getGroup($group, $languageCode, $organizationId),
            'group'        => $group,
            'language'     => $languageCode,
        ]);
    }

    public function updateTranslation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key'           => 'required|string',
            'value'         => 'required|string',
            'language_code' => 'required|string|max:10',
        ]);

        $translation = Translation::set(
            $validated['key'],
            $validated['value'],
            $validated['language_code'],
            $request->user()->organization_id
        );

        return $this->success($translation);
    }

    public function updateTranslations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language_code'          => 'required|string|max:10',
            'translations'           => 'required|array',
            'translations.*.key'     => 'required|string',
            'translations.*.value'   => 'required|string',
        ]);

        $languageCode   = $validated['language_code'];
        $organizationId = $request->user()->organization_id;
        $count          = 0;

        foreach ($validated['translations'] as $item) {
            Translation::set($item['key'], $item['value'], $languageCode, $organizationId);
            $count++;
        }

        Translation::clearCache();

        return $this->success(['count' => $count], "Updated {$count} translations");
    }

    public function getBranding(Request $request): JsonResponse
    {
        $branding = OrganizationBranding::getForOrganization($request->user()->organization_id);

        return $this->success([
            'branding'             => $branding,
            'css_variables'        => $branding->getCssVariables(),
            'presets'              => OrganizationBranding::COLOR_PRESETS,
            'font_options'         => OrganizationBranding::FONT_OPTIONS,
            'arabic_font_options'  => OrganizationBranding::ARABIC_FONT_OPTIONS,
        ]);
    }

    public function updateBranding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'primary_color'       => 'nullable|string|max:20',
            'secondary_color'     => 'nullable|string|max:20',
            'accent_color'        => 'nullable|string|max:20',
            'danger_color'        => 'nullable|string|max:20',
            'warning_color'       => 'nullable|string|max:20',
            'success_color'       => 'nullable|string|max:20',
            'info_color'          => 'nullable|string|max:20',
            'text_color'          => 'nullable|string|max:20',
            'background_color'    => 'nullable|string|max:20',
            'sidebar_color'       => 'nullable|string|max:20',
            'header_color'        => 'nullable|string|max:20',
            'font_family'         => 'nullable|string|max:100',
            'font_family_arabic'  => 'nullable|string|max:100',
            'base_font_size'      => 'nullable|integer|min:10|max:20',
            'theme'               => 'nullable|string|in:light,dark,auto',
            'enable_dark_mode'    => 'nullable|boolean',
            'custom_css'          => 'nullable|string|max:10000',
            'email_header_color'  => 'nullable|string|max:20',
            'email_footer_text'   => 'nullable|string|max:500',
            'document_watermark'  => 'nullable|string|max:100',
            'document_footer_text' => 'nullable|string|max:500',
            'preset'              => 'nullable|string',
        ]);

        $branding = OrganizationBranding::getForOrganization($request->user()->organization_id);

        if (isset($validated['preset'])) {
            $branding->applyPreset($validated['preset']);
        }

        $branding->fill(array_except($validated, ['preset']) ?? collect($validated)->except('preset')->all());
        $branding->save();

        return $this->success([
            'branding'      => $branding,
            'css_variables' => $branding->getCssVariables(),
        ], 'Branding updated successfully');
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,gif,svg,webp|max:2048',
            'type' => 'required|string|in:logo,logo_dark,favicon,login_background',
        ]);

        $type           = $validated['type'];
        $organizationId = $request->user()->organization_id;

        $path = $request->file('logo')->store("organizations/{$organizationId}/branding", 'public');
        $url  = Storage::disk('public')->url($path);

        $branding = OrganizationBranding::getForOrganization($organizationId);

        $fieldMap = [
            'logo'             => 'logo_url',
            'logo_dark'        => 'logo_dark_url',
            'favicon'          => 'favicon_url',
            'login_background' => 'login_background_url',
        ];

        $field = $fieldMap[$type];

        if ($branding->$field) {
            $oldPath = str_replace(Storage::disk('public')->url(''), '', $branding->$field);
            Storage::disk('public')->delete($oldPath);
        }

        $branding->$field = $url;
        $branding->save();

        return $this->success(['url' => $url], 'Logo uploaded successfully');
    }

    public function translationGroups(): JsonResponse
    {
        return $this->success(Translation::getGroups());
    }

    public function setUserLanguage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language_code' => 'required|string|max:10|exists:languages,code',
        ]);

        $user = $request->user();
        $user->preferred_language = $validated['language_code'];
        $user->save();

        return $this->success(['language' => $user->preferred_language], 'Language preference updated');
    }
}
