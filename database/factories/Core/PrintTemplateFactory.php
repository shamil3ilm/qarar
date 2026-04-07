<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\PrintTemplate;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrintTemplateFactory extends Factory
{
    protected $model = PrintTemplate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Template',
            'code' => fake()->unique()->slug(2),
            'document_type' => fake()->randomElement(['invoice', 'quotation', 'receipt', 'delivery_note']),
            'paper_size' => fake()->randomElement(['A4', 'A5', 'letter']),
            'orientation' => fake()->randomElement(['portrait', 'landscape']),
            'template_content' => '<html><body>{{content}}</body></html>',
            'template_file' => null,
            'settings' => null,
            'sections' => null,
            'show_logo' => true,
            'show_qr_code' => false,
            'show_signature' => false,
            'show_watermark' => false,
            'watermark_text' => null,
            'primary_color' => fake()->hexColor(),
            'secondary_color' => null,
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
