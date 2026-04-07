<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\SupportTicket;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'ticket_number' => 'TKT-' . fake()->unique()->numerify('######'),
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'assigned_admin_id' => null,
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['billing', 'technical', 'feature', 'general']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => fake()->randomElement(['open', 'in_progress', 'waiting', 'resolved', 'closed']),
            'tags' => null,
            'source' => fake()->randomElement(['email', 'web', 'chat']),
            'first_response_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'satisfaction_rating' => null,
            'satisfaction_feedback' => null,
        ];
    }
}