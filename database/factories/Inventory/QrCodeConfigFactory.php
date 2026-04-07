<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\QrCodeConfig;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class QrCodeConfigFactory extends Factory
{
    protected $model = QrCodeConfig::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'entity_type' => fake()->randomElement(['product', 'invoice', 'shelf_label']),
            'name' => fake()->words(3, true) . ' QR Config',
            'content_type' => fake()->randomElement(['url', 'json', 'text', 'vcard']),
            'content_template' => '{{entity_url}}',
            'included_fields' => ['name', 'sku', 'price'],
            'size_px' => fake()->randomElement([200, 300, 400, 500]),
            'foreground_color' => '#000000',
            'background_color' => '#FFFFFF',
            'logo_path' => null,
            'error_correction' => fake()->randomElement(['L', 'M', 'Q', 'H']),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
