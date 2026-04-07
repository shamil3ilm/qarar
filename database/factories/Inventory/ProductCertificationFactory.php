<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\ProductCertification;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductCertificationFactory extends Factory
{
    protected $model = ProductCertification::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'certification_name' => fake()->randomElement(['ISO 9001', 'HACCP', 'CE Mark', 'FDA Approved', 'GCC Conformity']),
            'certification_body' => fake()->company(),
            'certificate_number' => fake()->bothify('CERT-####-??'),
            'issued_date' => fake()->dateTimeBetween('-3 years', '-6 months'),
            'expiry_date' => fake()->optional(0.7)->dateTimeBetween('+1 month', '+3 years'),
            'certificate_file_path' => fake()->optional(0.3)->filePath(),
            'status' => fake()->randomElement(['active', 'expired', 'pending', 'revoked']),
        ];
    }
}
