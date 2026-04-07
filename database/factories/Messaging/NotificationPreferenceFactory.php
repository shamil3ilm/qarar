<?php

declare(strict_types=1);

namespace Database\Factories\Messaging;

use App\Models\Messaging\NotificationPreference;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'contact_id' => null,
            'email_enabled' => true,
            'sms_enabled' => true,
            'whatsapp_enabled' => false,
            'push_enabled' => false,
            'marketing_enabled' => true,
            'transactional_enabled' => true,
            'reminder_enabled' => true,
            'preferred_channel' => 'email',
            'preferred_language' => 'en',
            'timezone' => 'Asia/Riyadh',
            'quiet_hours' => null,
            'unsubscribed_at' => null,
            'unsubscribe_reason' => null,
        ];
    }
}
