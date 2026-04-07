<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskAttachment;
use App\Models\TaskBoard\BoardTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskAttachmentFactory extends Factory
{
    protected $model = TaskAttachment::class;

    public function definition(): array
    {
        return [
            'task_id' => BoardTask::factory(),
            'uploaded_by' => User::factory(),
            'file_name' => fake()->slug() . '.' . fake()->fileExtension(),
            'file_path' => 'task-files/' . fake()->uuid() . '.pdf',
            'file_type' => fake()->randomElement(['pdf', 'png', 'jpg', 'xlsx', 'docx']),
            'file_size' => fake()->numberBetween(1024, 10485760),
            'is_cover' => false,
        ];
    }
}
