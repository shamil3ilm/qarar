<?php

declare(strict_types=1);

namespace Database\Factories\Document;

use App\Models\Document\DigitalSignature;
use App\Models\Document\Document;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class DigitalSignatureFactory extends Factory
{
    protected $model = DigitalSignature::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'document_id' => Document::factory(),
            'signer_id' => null,
            'signer_email' => fake()->safeEmail(),
            'signer_name' => fake()->name(),
            'status' => fake()->randomElement(['pending', 'signed', 'declined', 'expired']),
            'signature_data' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'signed_at' => fake()->optional(0.5)->dateTimeBetween('-1 month', 'now'),
            'expires_at' => fake()->dateTimeBetween('+1 week', '+3 months'),
            'verification_code' => fake()->numerify('######'),
        ];
    }
}
