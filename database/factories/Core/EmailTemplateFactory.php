<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\EmailTemplate;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true) . ' Email',
            'subject' => fake()->sentence(6),
            'body_html' => '<p>' . fake()->paragraph() . '</p>',
            'body_text' => fake()->paragraph(),
            'from_name' => fake()->optional(0.3)->company(),
            'reply_to' => fake()->optional(0.3)->safeEmail(),
            'cc' => null,
            'bcc' => null,
            'variables' => ['name', 'company', 'amount'],
            'language' => 'en',
            'is_active' => true,
            'is_system' => false,
        ];
    }
}
