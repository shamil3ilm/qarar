<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // EPF (Employees' Provident Fund) contributions
        Schema::create('epf_contributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->string('uan', 12)->nullable();                    // Universal Account Number (EPFO)
            $table->decimal('pf_wage', 12, 2);                        // PF wage (basic + DA, capped at 15000)
            $table->decimal('employee_contribution', 12, 2);          // 12% of PF wage
            $table->decimal('employer_epf_contribution', 12, 2);      // 3.67% diff after EPS
            $table->decimal('employer_eps_contribution', 12, 2);      // 8.33% EPS (max ₹1250/month)
            $table->decimal('edli_contribution', 12, 2);              // 0.50% EDLI employer
            $table->decimal('admin_charges', 12, 2)->default(0);      // 0.50% EPF admin
            $table->enum('status', ['draft', 'submitted', 'challan_paid'])->default('draft');
            $table->string('challan_number', 50)->nullable();
            $table->date('challan_due_date')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'employee_id', 'payroll_period_id'],
                'epf_contrib_org_emp_period_uniq'
            );
            $table->index(['organization_id', 'payroll_period_id'], 'epf_contrib_org_period_idx');
        });

        // ESI (Employees' State Insurance) contributions
        Schema::create('esi_contributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->string('ip_number', 17)->nullable();              // Insurance Policy Number (ESIC)
            $table->decimal('gross_wage', 12, 2);
            $table->decimal('employee_contribution', 12, 2);         // 0.75% of gross wage
            $table->decimal('employer_contribution', 12, 2);         // 3.25% of gross wage
            $table->boolean('is_applicable')->default(true);         // false when gross > ₹21,000
            $table->enum('status', ['draft', 'submitted', 'challan_paid'])->default('draft');
            $table->string('challan_number', 50)->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'employee_id', 'payroll_period_id'],
                'esi_contrib_org_emp_period_uniq'
            );
            $table->index(['organization_id', 'payroll_period_id'], 'esi_contrib_org_period_idx');
        });

        // Professional Tax slab configuration per Indian state
        Schema::create('professional_tax_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('state_code', 2);          // ISO IN-state: KA, MH, WB, TN, AP, TS...
            $table->decimal('salary_from', 12, 2);
            $table->decimal('salary_to', 12, 2)->nullable();  // null = no upper limit
            $table->decimal('monthly_tax', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'state_code'], 'pt_config_org_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_tax_configs');
        Schema::dropIfExists('esi_contributions');
        Schema::dropIfExists('epf_contributions');
    }
};
