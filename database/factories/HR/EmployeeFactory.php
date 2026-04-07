<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        $joiningDate = fake()->dateTimeBetween('-5 years', '-1 month');

        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'user_id' => null,
            'employee_number' => 'EMP-' . strtoupper(fake()->unique()->bothify('??###??')),
            'first_name' => $firstName,
            'middle_name' => fake()->optional(0.3)->firstName(),
            'last_name' => $lastName,
            'display_name' => "{$firstName} {$lastName}",
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-22 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'marital_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed']),
            'nationality' => fake()->randomElement(['SA', 'IN', 'PK', 'EG', 'PH', 'BD']),
            'blood_group' => fake()->optional(0.5)->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
            'email' => fake()->unique()->companyEmail(),
            'personal_email' => fake()->optional(0.6)->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile' => fake()->phoneNumber(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'emergency_contact_relation' => fake()->randomElement(['spouse', 'parent', 'sibling', 'friend']),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional(0.3)->secondaryAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country_code' => fake()->randomElement(['SA', 'AE', 'IN']),
            'department_id' => null,
            'designation_id' => null,
            'reporting_manager_id' => null,
            'joining_date' => $joiningDate,
            'confirmation_date' => fake()->optional(0.7)->dateTimeBetween($joiningDate, 'now'),
            'termination_date' => null,
            'termination_reason' => null,
            'employment_type' => fake()->randomElement([
                Employee::EMPLOYMENT_TYPE_FULL_TIME,
                Employee::EMPLOYMENT_TYPE_PART_TIME,
                Employee::EMPLOYMENT_TYPE_CONTRACT,
            ]),
            'employment_status' => Employee::STATUS_ACTIVE,
            'work_schedule' => fake()->randomElement(['standard', 'flexible', 'shift']),
            'shift_start' => '09:00',
            'shift_end' => '18:00',
            'work_days' => [1, 2, 3, 4, 5],
            'national_id' => fake()->numerify('##########'),
            'passport_number' => fake()->optional(0.5)->bothify('??######'),
            'passport_expiry' => fake()->optional(0.5)->dateTimeBetween('+1 year', '+10 years'),
            'visa_number' => null,
            'visa_expiry' => null,
            'work_permit_number' => null,
            'work_permit_expiry' => null,
            'tax_number' => fake()->optional(0.4)->numerify('TIN-##########'),
            'social_security_number' => null,
            'tax_declarations' => null,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'payment_mode' => fake()->randomElement(['bank_transfer', 'cheque', 'cash']),
            'bank_name' => fake()->optional(0.7)->company(),
            'bank_account_number' => fake()->optional(0.7)->numerify('################'),
            'bank_ifsc_code' => null,
            'bank_iban' => fake()->optional(0.5)->iban('SA'),
            'notes' => null,
            'profile_photo_path' => null,
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn () => [
            'employment_status' => Employee::STATUS_TERMINATED,
            'termination_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'termination_reason' => fake()->sentence(),
            'is_active' => false,
        ]);
    }

    public function onProbation(): static
    {
        return $this->state(fn () => [
            'employment_type' => Employee::EMPLOYMENT_TYPE_PROBATION,
            'confirmation_date' => null,
            'joining_date' => fake()->dateTimeBetween('-3 months', '-1 week'),
        ]);
    }

    public function fullTime(): static
    {
        return $this->state(fn () => [
            'employment_type' => Employee::EMPLOYMENT_TYPE_FULL_TIME,
        ]);
    }

    public function contractor(): static
    {
        return $this->state(fn () => [
            'employment_type' => Employee::EMPLOYMENT_TYPE_CONTRACT,
        ]);
    }
}
