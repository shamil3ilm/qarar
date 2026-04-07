<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(PlatformSetting::all());
    }

    public function show(string $key): JsonResponse
    {
        $setting = PlatformSetting::where('key', $key)->firstOrFail();
        return $this->success($setting);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $setting = PlatformSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $request->input('value'), 'group' => $request->input('group', 'general')]
        );
        return $this->success($setting);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        foreach ($request->input('settings', []) as $key => $value) {
            PlatformSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        return $this->success(['message' => 'Settings updated']);
    }
}
