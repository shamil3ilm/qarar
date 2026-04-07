<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'notification_type' => fake()->randomElement(['invoice_created', 'payment_received', 'low_stock']),
            'email_enabled' => true,
            'database_enabled' => true,
            'push_enabled' => false,
            'sms_enabled' => false,
        ];
    }
}
