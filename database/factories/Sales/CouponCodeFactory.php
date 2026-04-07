<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\CouponCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponCodeFactory extends Factory
{
    protected $model = CouponCode::class;

    public function definition(): array
    {
        return [
            'promotion_id' => fake()->randomNumber(3, true),
            'code' => strtoupper(fake()->unique()->bothify('CPN-????####')),
            'max_uses' => fake()->optional(0.6)->numberBetween(1, 100),
            'times_used' => 0,
            'assigned_to_contact_id' => null,
            'is_active' => true,
            'expires_at' => fake()->optional(0.5)->dateTimeBetween('+7 days', '+6 months'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
            'expires_at' => now()->addMonths(3),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function singleUse(): static
    {
        return $this->state(fn () => [
            'max_uses' => 1,
        ]);
    }

    public function unlimited(): static
    {
        return $this->state(fn () => [
            'max_uses' => null,
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn () => [
            'max_uses' => 5,
            'times_used' => 5,
            'is_active' => false,
        ]);
    }
}
