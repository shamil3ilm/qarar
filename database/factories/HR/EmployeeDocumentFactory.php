<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\EmployeeDocument;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'document_type' => fake()->randomElement(['passport', 'visa', 'id_card', 'contract', 'certificate']),
            'document_name' => fake()->words(3, true),
            'document_number' => fake()->bothify('DOC-####??'),
            'issue_date' => fake()->dateTimeBetween('-5 years', '-1 year'),
            'expiry_date' => fake()->optional(0.7)->dateTimeBetween('+1 month', '+5 years'),
            'file_path' => 'documents/' . fake()->uuid() . '.pdf',
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}
