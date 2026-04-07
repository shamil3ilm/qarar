<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\ProductReview;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductReviewFactory extends Factory
{
    protected $model = ProductReview::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'product_id' => Product::factory(),
            'contact_id' => null,
            'reviewer_name' => fake()->name(),
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->optional(0.5)->sentence(4),
            'review_text' => fake()->optional(0.7)->paragraph(),
            'pros' => fake()->optional(0.3)->words(3),
            'cons' => fake()->optional(0.3)->words(2),
            'is_verified_purchase' => fake()->boolean(50),
            'invoice_id' => null,
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
