<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * Get notification preferences for a contact.
     */
    public function show(Request $request, int $contactId): JsonResponse
    {
        $preference = NotificationPreference::where('contact_id', $contactId)->first();

        if (!$preference) {
            // Return default preferences if none exist
            return $this->success([
                'contact_id' => $contactId,
                'email_enabled' => true,
                'sms_enabled' => true,
                'whatsapp_enabled' => true,
                'push_enabled' => true,
                'marketing_enabled' => true,
                'transactional_enabled' => true,
                'reminder_enabled' => true,
                'preferred_channel' => 'email',
                'preferred_language' => 'en',
                'timezone' => null,
                'quiet_hours' => null,
                'unsubscribed_at' => null,
            ], 'Default notification preferences.');
        }

        return $this->success($preference);
    }

    /**
     * Update notification preferences for a contact (create or update).
     */
    public function update(Request $request, int $contactId): JsonResponse
    {
        $validated = $request->validate([
            'email_enabled' => 'nullable|boolean',
            'sms_enabled' => 'nullable|boolean',
            'whatsapp_enabled' => 'nullable|boolean',
            'push_enabled' => 'nullable|boolean',
            'marketing_enabled' => 'nullable|boolean',
            'transactional_enabled' => 'nullable|boolean',
            'reminder_enabled' => 'nullable|boolean',
            'preferred_channel' => 'nullable|in:email,sms,whatsapp,push_notification',
            'preferred_language' => 'nullable|string|max:5',
            'timezone' => 'nullable|string|max:255',
            'quiet_hours' => 'nullable|array',
            'quiet_hours.start' => 'required_with:quiet_hours|string',
            'quiet_hours.end' => 'required_with:quiet_hours|string',
        ]);

        $validated['contact_id'] = $contactId;

        $preference = NotificationPreference::updateOrCreate(
            ['contact_id' => $contactId],
            $validated
        );

        return $this->success($preference, 'Notification preferences updated successfully.');
    }

    /**
     * Unsubscribe a contact from all messaging.
     */
    public function unsubscribe(Request $request, int $contactId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $preference = NotificationPreference::firstOrCreate(
            ['contact_id' => $contactId],
            ['organization_id' => $this->organizationId($request)]
        );

        $preference->unsubscribe($validated['reason'] ?? null);

        return $this->success($preference, 'Contact unsubscribed successfully.');
    }

    /**
     * Resubscribe a contact.
     */
    public function resubscribe(int $contactId): JsonResponse
    {
        $preference = NotificationPreference::where('contact_id', $contactId)->first();

        if (!$preference) {
            return $this->notFound('No preferences found for this contact.');
        }

        $preference->resubscribe();

        return $this->success($preference, 'Contact resubscribed successfully.');
    }
}
