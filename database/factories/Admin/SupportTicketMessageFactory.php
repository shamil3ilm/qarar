<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\SupportTicketMessage;
use App\Models\Admin\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportTicketMessageFactory extends Factory
{
    protected $model = SupportTicketMessage::class;

    public function definition(): array
    {
        return [
            'ticket_id' => SupportTicket::factory(),
            'user_id' => null,
            'admin_id' => null,
            'message' => fake()->paragraph(),
            'is_internal_note' => false,
            'attachments' => null,
        ];
    }
}