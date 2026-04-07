<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // Gap 7: Time Evaluation / CATS
        // ---------------------------------------------------------------

        Schema::create('time_wage_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 10); // OT15=1.5x overtime, NT=night diff, WE=weekend
            $table->string('name', 100);
            $table->enum('wage_category', [
                'overtime',
                'night_differential',
                'weekend',
                'holiday',
                'absence_deduction',
                'other',
            ])->default('other');
            $table->decimal('rate_multiplier', 5, 4)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'twt_org_code_unique');
        });

        Schema::create('time_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'transferred_to_payroll',
            ])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->decimal('total_regular_hours', 8, 2)->default(0);
            $table->decimal('total_overtime_hours', 8, 2)->default(0);
            $table->decimal('total_absence_hours', 8, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['employee_id', 'period_start', 'period_end'], 'ts_emp_period_unique');
            $table->index(['organization_id', 'status'], 'ts_org_status_idx');
        });

        Schema::create('time_sheet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_sheet_id')->constrained('time_sheets')->cascadeOnDelete();
            $table->date('entry_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('hours', 5, 2);
            $table->enum('entry_type', [
                'regular',
                'overtime',
                'absence',
                'holiday',
                'training',
            ])->default('regular');
            $table->foreignId('wage_type_id')->nullable()->constrained('time_wage_types')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('wbs_element_id')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->string('activity_code', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['time_sheet_id', 'entry_date'], 'tse_sheet_date_idx');
            $table->index(['cost_center_id', 'entry_date'], 'tse_cc_date_idx');
        });

        Schema::create('time_evaluation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_sheet_id')->constrained('time_sheets')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('evaluation_date');
            $table->foreignId('wage_type_id')->constrained('time_wage_types')->cascadeOnDelete();
            $table->decimal('hours', 5, 2);
            $table->decimal('amount', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->boolean('transferred_to_payroll')->default(false);
            $table->timestamps();
            $table->index(['time_sheet_id'], 'ter_sheet_idx');
            $table->index(['employee_id', 'evaluation_date'], 'ter_emp_date_idx');
        });

        // ---------------------------------------------------------------
        // Gap 8: Travel Expense Management
        // ---------------------------------------------------------------

        Schema::create('per_diem_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('destination_country', 3); // ISO country code
            $table->string('destination_city', 100)->nullable();
            $table->decimal('daily_allowance', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->enum('meal_allowance_type', ['included', 'separate'])->default('included');
            $table->decimal('meal_breakfast', 15, 4)->default(0);
            $table->decimal('meal_lunch', 15, 4)->default(0);
            $table->decimal('meal_dinner', 15, 4)->default(0);
            $table->decimal('mileage_rate', 10, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(
                ['organization_id', 'destination_country', 'destination_city'],
                'pdr_org_dest_unique'
            );
        });

        Schema::create('travel_requests', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('request_number', 30);
            $table->string('purpose', 500);
            $table->date('departure_date');
            $table->date('return_date');
            $table->string('destination_country', 3);
            $table->string('destination_city', 100)->nullable();
            $table->enum('travel_type', ['domestic', 'international'])->default('domestic');
            $table->decimal('estimated_cost', 15, 4)->default(0);
            $table->decimal('advance_requested', 15, 4)->default(0);
            $table->decimal('advance_approved', 15, 4)->default(0);
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'completed',
                'cancelled',
            ])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'request_number'], 'tr_org_number_unique');
            $table->index(['employee_id', 'status'], 'tr_emp_status_idx');
        });

        Schema::create('travel_expense_claims', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('travel_request_id')->nullable()->constrained('travel_requests')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('claim_number', 30);
            $table->date('claim_date');
            $table->decimal('total_claimed', 15, 4)->default(0);
            $table->decimal('advance_paid', 15, 4)->default(0);
            $table->decimal('amount_reimbursable', 15, 4)->default(0);
            $table->decimal('amount_deductible', 15, 4)->default(0);
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'paid',
            ])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'claim_number'], 'tec_org_number_unique');
            $table->index(['employee_id', 'status'], 'tec_emp_status_idx');
        });

        Schema::create('travel_expense_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('travel_expense_claims')->cascadeOnDelete();
            $table->date('expense_date');
            $table->enum('expense_category', [
                'flight',
                'hotel',
                'meal',
                'transport',
                'per_diem',
                'mileage',
                'visa',
                'other',
            ])->default('other');
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 15, 4);
            $table->decimal('mileage_km', 10, 2)->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('amount_in_base_currency', 15, 4)->default(0);
            $table->string('receipt_reference', 100)->nullable();
            $table->boolean('receipt_attached')->default(false);
            $table->decimal('policy_limit', 15, 4)->nullable();
            $table->boolean('within_policy')->default(true);
            $table->timestamps();
            $table->index(['claim_id'], 'tel_claim_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_expense_lines');
        Schema::dropIfExists('travel_expense_claims');
        Schema::dropIfExists('travel_requests');
        Schema::dropIfExists('per_diem_rates');
        Schema::dropIfExists('time_evaluation_results');
        Schema::dropIfExists('time_sheet_entries');
        Schema::dropIfExists('time_sheets');
        Schema::dropIfExists('time_wage_types');
    }
};
