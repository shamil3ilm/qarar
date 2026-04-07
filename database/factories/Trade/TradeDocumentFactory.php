<?php

declare(strict_types=1);

namespace Database\Factories\Trade;

use App\Models\Trade\TradeDocument;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class TradeDocumentFactory extends Factory
{
    protected $model = TradeDocument::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'document_type' => fake()->randomElement(['bill_of_lading', 'certificate_of_origin', 'commercial_invoice', 'packing_list']),
            'document_number' => fake()->bothify('TD-####-??'),
            'reference' => fake()->optional(0.3)->bothify('REF-####'),
            'source_type' => null,
            'source_id' => null,
            'contact_id' => null,
            'issued_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'expiry_date' => fake()->optional(0.5)->dateTimeBetween('+1 month', '+1 year'),
            'issuing_authority' => fake()->optional(0.5)->company(),
            'issuing_country' => fake()->countryCode(),
            'file_path' => 'trade-docs/' . fake()->uuid() . '.pdf',
            'file_type' => 'pdf',
            'file_size' => fake()->numberBetween(1024, 5242880),
            'status' => fake()->randomElement(['draft', 'issued', 'verified', 'expired']),
            'notes' => fake()->optional(0.3)->sentence(),
            'created_by' => null,
        ];
    }
}
