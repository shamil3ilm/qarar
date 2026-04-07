<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_insurance_schemes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('country_code', 10);
            $table->string('scheme_code', 20)->nullable();
            $table->decimal('employee_contribution_pct', 5, 2)->default(0);
            $table->decimal('employer_contribution_pct', 5, 2)->default(0);
            $table->decimal('work_hazard_pct', 5, 2)->default(0);
            $table->enum('applicable_to', ['all', 'nationals_only', 'expats_only'])->default('all');
            $table->decimal('salary_ceiling', 15, 4)->nullable();
            $table->decimal('salary_floor', 15, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'country_code'], 'si_sch_org_country_idx');
        });

        Schema::create('social_insurance_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained('social_insurance_schemes')->cascadeOnDelete();
            $table->string('employee_number_si', 50)->nullable();
            $table->date('enrollment_date');
            $table->date('termination_date')->nullable();
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->decimal('insurable_salary', 15, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'scheme_id'], 'si_rec_emp_scheme_uniq');
            $table->index(['organization_id', 'status'], 'si_rec_org_status_idx');
        });

        Schema::create('social_insurance_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained('social_insurance_schemes')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedInteger('total_employees')->default(0);
            $table->decimal('total_insurable_salary', 15, 4)->default(0);
            $table->decimal('total_employee_contrib', 15, 4)->default(0);
            $table->decimal('total_employer_contrib', 15, 4)->default(0);
            $table->decimal('total_work_hazard_contrib', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->enum('status', ['draft', 'submitted', 'acknowledged', 'rejected'])->default('draft');
            $table->string('reference_number', 100)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'scheme_id', 'period_year', 'period_month'], 'si_sub_org_sch_period_uniq');
            $table->index(['organization_id', 'status'], 'si_sub_org_status_idx');
        });

        Schema::create('social_insurance_submission_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('submission_id')->constrained('social_insurance_submissions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('record_id')->constrained('social_insurance_records')->cascadeOnDelete();
            $table->string('employee_number_si', 50)->nullable();
            $table->decimal('insurable_salary', 15, 4)->default(0);
            $table->decimal('employee_contribution', 15, 4)->default(0);
            $table->decimal('employer_contribution', 15, 4)->default(0);
            $table->decimal('work_hazard_contribution', 15, 4)->default(0);
            $table->decimal('total_contribution', 15, 4)->default(0);
            $table->timestamps();

            $table->index(['submission_id', 'employee_id'], 'si_line_sub_emp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_insurance_submission_lines');
        Schema::dropIfExists('social_insurance_submissions');
        Schema::dropIfExists('social_insurance_records');
        Schema::dropIfExists('social_insurance_schemes');
    }
};
