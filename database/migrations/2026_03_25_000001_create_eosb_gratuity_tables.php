<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eosb_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('country_code', 10);
            $table->enum('calculation_method', ['saudi', 'uae', 'qatar', 'kuwait', 'bahrain', 'oman', 'india']);
            $table->unsignedSmallInteger('min_service_months')->default(12);
            $table->decimal('first_period_days_per_year', 5, 2)->default(15.00);
            $table->unsignedSmallInteger('first_period_years')->default(5);
            $table->decimal('subsequent_days_per_year', 5, 2)->default(30.00);
            $table->boolean('prorate_partial_year')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'country_code'], 'eosb_pol_org_country_idx');
        });

        Schema::create('eosb_provisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('eosb_policy_id')->constrained('eosb_policies')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('days_earned', 8, 4)->default(0);
            $table->decimal('daily_rate', 15, 4)->default(0);
            $table->decimal('provision_amount', 15, 4)->default(0);
            $table->decimal('cumulative_amount', 15, 4)->default(0);
            $table->decimal('basic_salary_used', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'period_year', 'period_month'], 'eosb_prov_emp_period_uniq');
            $table->index(['organization_id', 'period_year', 'period_month'], 'eosb_prov_org_period_idx');
        });

        Schema::create('eosb_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('eosb_policy_id')->constrained('eosb_policies')->cascadeOnDelete();
            $table->date('termination_date');
            $table->decimal('years_of_service', 8, 4);
            $table->decimal('total_days_earned', 8, 4);
            $table->decimal('daily_rate', 15, 4);
            $table->decimal('gross_amount', 15, 4);
            $table->decimal('deductions', 15, 4)->default(0);
            $table->decimal('net_amount', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('payment_date')->nullable();
            $table->enum('status', ['draft', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'eosb_set_org_status_idx');
            $table->index('employee_id', 'eosb_set_emp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eosb_settlements');
        Schema::dropIfExists('eosb_provisions');
        Schema::dropIfExists('eosb_policies');
    }
};
