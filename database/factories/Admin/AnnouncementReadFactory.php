<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\AnnouncementRead;
use App\Models\Admin\SystemAnnouncement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnnouncementReadFactory extends Factory
{
    protected $model = AnnouncementRead::class;

    public function definition(): array
    {
        return [
            'announcement_id' => SystemAnnouncement::factory(),
            'user_id' => User::factory(),
            'is_dismissed' => false,
        ];
    }
}