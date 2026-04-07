<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payroll_corrections');

        Schema::create('payroll_corrections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->name('pc_employee_fk');
            $table->foreignId('original_payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete()->name('pc_orig_period_fk');
            $table->foreignId('correction_payroll_period_id')->nullable()->constrained('payroll_periods')->nullOnDelete()->name('pc_corr_period_fk');
            $table->enum('correction_type', ['salary_change', 'component_adjustment', 'tax_correction', 'deduction_adjustment'])->default('salary_change');
            $table->enum('status', ['draft', 'approved', 'posted', 'cancelled'])->default('draft');
            $table->decimal('original_amount', 18, 4);
            $table->decimal('corrected_amount', 18, 4);
            $table->decimal('difference_amount', 18, 4);
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->name('pc_approved_by_fk');
            $table->datetime('approved_at')->nullable();
            $table->datetime('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'employee_id'], 'pc_org_employee_idx');
            $table->index(['original_payroll_period_id'], 'pc_orig_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_corrections');
    }
};
