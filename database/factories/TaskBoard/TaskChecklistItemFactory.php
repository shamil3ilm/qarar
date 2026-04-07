<?php

declare(strict_types=1);

namespace Database\Factories\TaskBoard;

use App\Models\TaskBoard\TaskChecklistItem;
use App\Models\TaskBoard\TaskChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskChecklistItemFactory extends Factory
{
    protected $model = TaskChecklistItem::class;

    public function definition(): array
    {
        return [
            'checklist_id' => TaskChecklist::factory(),
            'content' => fake()->sentence(),
            'is_completed' => false,
            'completed_by' => null,
            'completed_at' => null,
            'assignee_id' => null,
            'due_date' => fake()->optional(0.3)->dateTimeBetween('now', '+2 weeks'),
            'position' => fake()->numberBetween(0, 20),
        ];
    }
}
