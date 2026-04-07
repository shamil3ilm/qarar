<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskLabelAssignment;
use App\Models\TaskBoard\BoardTask;
use App\Models\TaskBoard\TaskLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskLabelAssignmentFactory extends Factory
{
    protected $model = TaskLabelAssignment::class;

    public function definition(): array
    {
        return [
            'task_id' => BoardTask::factory(),
            'label_id' => TaskLabel::factory(),
        ];
    }
}
