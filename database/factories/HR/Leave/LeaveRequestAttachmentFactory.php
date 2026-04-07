<?php

declare(strict_types=1);

namespace Database\Factories\HR\Leave;

use App\Models\HR\Leave\LeaveRequestAttachment;
use App\Models\HR\Leave\LeaveRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestAttachmentFactory extends Factory
{
    protected $model = LeaveRequestAttachment::class;

    public function definition(): array
    {
        return [
            'leave_request_id' => LeaveRequest::factory(),
            'file_name' => fake()->slug() . '.pdf',
            'file_path' => 'leave-attachments/' . fake()->uuid() . '.pdf',
            'file_type' => fake()->randomElement(['pdf', 'jpg', 'png']),
            'file_size' => fake()->numberBetween(1024, 5242880),
            'uploaded_by' => null,
        ];
    }
}
