<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\CreditNoteApplication;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditNoteApplicationFactory extends Factory
{
    protected $model = CreditNoteApplication::class;

    public function definition(): array
    {
        return [
            'credit_note_id' => CreditNote::factory(),
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->randomFloat(2, 10, 10000),
            'applied_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
