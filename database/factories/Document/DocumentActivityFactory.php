<?php

declare(strict_types=1);

namespace Database\Factories\Document;

use App\Models\Document\DocumentActivity;
use App\Models\Document\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentActivityFactory extends Factory
{
    protected $model = DocumentActivity::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['viewed', 'downloaded', 'edited', 'shared', 'deleted']),
            'metadata' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }
}
