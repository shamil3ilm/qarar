<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eosb_calculations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('policy_id')->nullable()->constrained('eosb_policies')->nullOnDelete();
            $table->date('calculation_date');
            $table->decimal('service_years', 8, 2);
            $table->decimal('last_basic_salary', 15, 2);
            $table->decimal('last_total_salary', 15, 2);
            $table->decimal('gratuity_amount', 15, 2);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->string('currency_code', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'eosb_calc_org_status_idx');
            $table->index('employee_id', 'eosb_calc_emp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eosb_calculations');
    }
};
