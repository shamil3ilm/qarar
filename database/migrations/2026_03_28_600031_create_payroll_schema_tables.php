<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payroll_processing_runs');
        Schema::dropIfExists('wage_type_rules');
        Schema::dropIfExists('wage_type_catalog');
        Schema::dropIfExists('payroll_schema_steps');
        Schema::dropIfExists('payroll_schemas');

        Schema::create('payroll_schemas', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('schema_name');
            $table->text('description')->nullable();
            $table->char('country_code', 2);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payroll_schema_steps', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('payroll_schema_id');
            $table->foreign('payroll_schema_id', 'pr_step_schema_fk')->references('id')->on('payroll_schemas')->onDelete('cascade');
            $table->unsignedSmallInteger('step_number');
            $table->string('function_name', 10);
            $table->string('parameter')->nullable();
            $table->string('condition_wage_type')->nullable();
            $table->string('processing_class')->nullable();
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('wage_type_catalog', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('wage_type_code', 4);
            $table->string('description');
            $table->enum('category', ['earnings', 'deductions', 'employer_contributions', 'informational']);
            $table->string('processing_class', 2)->nullable();
            $table->string('evaluation_class', 2)->nullable();
            $table->string('cumulation_class', 2)->nullable();
            $table->boolean('taxable')->default(true);
            $table->boolean('pensionable')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'wage_type_code'], 'wage_type_org_code_unique');
        });

        Schema::create('wage_type_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('wage_type_id');
            $table->foreign('wage_type_id', 'wt_rule_wt_fk')->references('id')->on('wage_type_catalog')->onDelete('cascade');
            $table->enum('rule_type', ['amount', 'percentage', 'formula']);
            $table->json('base_wage_types')->nullable();
            $table->decimal('calculation_factor', 8, 6)->default(1.0);
            $table->string('formula')->nullable();
            $table->unsignedSmallInteger('priority');
            $table->timestamps();
        });

        Schema::create('payroll_processing_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('schema_id');
            $table->foreign('schema_id', 'pr_run_schema_fk')->references('id')->on('payroll_schemas')->onDelete('restrict');
            $table->unsignedBigInteger('payroll_period_id');
            $table->foreign('payroll_period_id', 'pr_run_period_fk')->references('id')->on('payroll_periods')->onDelete('restrict');
            $table->enum('run_type', ['simulation', 'live', 'correction']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('employee_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('run_by');
            $table->foreign('run_by', 'pr_run_usr_fk')->references('id')->on('users')->onDelete('restrict');
            $table->json('error_log')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_processing_runs');
        Schema::dropIfExists('wage_type_rules');
        Schema::dropIfExists('wage_type_catalog');
        Schema::dropIfExists('payroll_schema_steps');
        Schema::dropIfExists('payroll_schemas');
    }
};
