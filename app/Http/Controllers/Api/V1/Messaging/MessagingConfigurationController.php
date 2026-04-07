<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessagingConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessagingConfigurationController extends Controller
{
    /**
     * List messaging configurations (channels).
     */
    public function index(Request $request): JsonResponse
    {
        $query = MessagingConfiguration::query()
            ->when($request->channel_type, fn($q, $type) => $q->forChannel($type))
            ->when($request->provider, fn($q, $provider) => $q->forProvider($provider))
            ->when($request->is_active !== null, function ($q) use ($request) {
                return $request->is_active === 'true' ? $q->active() : $q->where('is_active', false);
            })
            ->orderBy('channel_type')
            ->orderBy('name');

        $configurations = $query->paginate((int) ($request->per_page ?? 15));

        return $this->paginated($configurations);
    }

    /**
     * Store a new messaging configuration.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_type' => 'required|in:email,sms,whatsapp,push_notification',
            'name' => 'required|string|max:255',
            'provider' => 'required|in:smtp,sendgrid,twilio,vonage,firebase,whatsapp_business',
            'credentials' => 'required|array',
            'settings' => 'nullable|array',
            'sender_name' => 'nullable|string|max:255',
            'sender_address' => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $configuration = DB::transaction(function () use ($validated) {
            // If setting as default, unset other defaults for this channel type
            if (!empty($validated['is_default'])) {
                MessagingConfiguration::where('channel_type', $validated['channel_type'])
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return MessagingConfiguration::create($validated);
        });

        return $this->created($configuration);
    }

    /**
     * Show a specific messaging configuration.
     */
    public function show(MessagingConfiguration $messagingConfiguration): JsonResponse
    {
        return $this->success($messagingConfiguration);
    }

    /**
     * Update a messaging configuration.
     */
    public function update(Request $request, MessagingConfiguration $messagingConfiguration): JsonResponse
    {
        $validated = $request->validate([
            'channel_type' => 'sometimes|in:email,sms,whatsapp,push_notification',
            'name' => 'sometimes|string|max:255',
            'provider' => 'sometimes|in:smtp,sendgrid,twilio,vonage,firebase,whatsapp_business',
            'credentials' => 'sometimes|array',
            'settings' => 'nullable|array',
            'sender_name' => 'nullable|string|max:255',
            'sender_address' => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($messagingConfiguration, $validated) {
            // If setting as default, unset other defaults for this channel type
            if (!empty($validated['is_default'])) {
                $channelType = $validated['channel_type'] ?? $messagingConfiguration->channel_type;
                MessagingConfiguration::where('channel_type', $channelType)
                    ->where('is_default', true)
                    ->where('id', '!=', $messagingConfiguration->id)
                    ->update(['is_default' => false]);
            }

            $messagingConfiguration->update($validated);
        });

        return $this->success(
            $messagingConfiguration->fresh(),
            'Messaging configuration updated successfully.'
        );
    }

    /**
     * Delete a messaging configuration.
     */
    public function destroy(MessagingConfiguration $messagingConfiguration): JsonResponse
    {
        if ($messagingConfiguration->isDefault()) {
            return $this->error(
                'Cannot delete the default channel configuration. Set another as default first.',
                'DEFAULT_CHANNEL',
                422
            );
        }

        $messagingConfiguration->delete();

        return $this->success(null, 'Messaging configuration deleted successfully.');
    }
}
