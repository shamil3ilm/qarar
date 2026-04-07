<?php

declare(strict_types=1);

namespace Database\Factories\Automation;

use App\Models\Automation\AutomationEmailTemplate;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationEmailTemplateFactory extends Factory
{
    protected $model = AutomationEmailTemplate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Template',
            'subject' => fake()->sentence(6),
            'body_html' => '<p>' . fake()->paragraph() . '</p>',
            'body_text' => fake()->paragraph(),
            'variables' => ['name', 'email', 'company'],
            'category' => fake()->randomElement(['sales', 'support', 'marketing', 'notification']),
            'is_active' => true,
        ];
    }
}