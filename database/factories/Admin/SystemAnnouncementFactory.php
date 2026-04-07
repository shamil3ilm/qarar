<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\SystemAnnouncement;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemAnnouncementFactory extends Factory
{
    protected $model = SystemAnnouncement::class;

    public function definition(): array
    {
        return [
            'admin_id' => null,
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'type' => fake()->randomElement(['info', 'warning', 'maintenance', 'feature', 'critical']),
            'target_audience' => 'all',
            'is_dismissible' => true,
            'show_banner' => false,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'is_active' => true,
        ];
    }
}
