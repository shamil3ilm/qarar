<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('hr_headcount_plans');
        Schema::dropIfExists('hr_budget_lines');
        Schema::dropIfExists('hr_budget_plans');

        Schema::create('hr_budget_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreign('department_id', 'hr_budget_dept_fk')->references('id')->on('departments')->onDelete('set null');
            $table->string('plan_name');
            $table->enum('status', ['draft', 'submitted', 'approved'])->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'hr_budget_appr_fk')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('total_headcount')->default(0);
            $table->decimal('total_salary_budget', 18, 4)->default(0);
            $table->decimal('total_benefits_budget', 18, 4)->default(0);
            $table->char('currency', 3);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hr_budget_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('hr_budget_plan_id');
            $table->foreign('hr_budget_plan_id', 'hr_budget_line_fk')->references('id')->on('hr_budget_plans')->onDelete('cascade');
            $table->unsignedBigInteger('position_id')->nullable();
            $table->foreign('position_id', 'hr_budget_line_pos_fk')->references('id')->on('positions')->onDelete('set null');
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->foreign('designation_id', 'hr_budget_line_des_fk')->references('id')->on('designations')->onDelete('set null');
            $table->unsignedSmallInteger('planned_headcount')->default(1);
            $table->decimal('planned_salary', 18, 4);
            $table->decimal('planned_benefits', 18, 4)->default(0);
            $table->decimal('quarter_1', 18, 4)->default(0);
            $table->decimal('quarter_2', 18, 4)->default(0);
            $table->decimal('quarter_3', 18, 4)->default(0);
            $table->decimal('quarter_4', 18, 4)->default(0);
            $table->unsignedSmallInteger('actual_headcount')->default(0);
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('hr_headcount_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedBigInteger('department_id');
            $table->foreign('department_id', 'hc_plan_dept_fk')->references('id')->on('departments')->onDelete('cascade');
            $table->tinyInteger('month');
            $table->unsignedSmallInteger('planned_headcount');
            $table->unsignedSmallInteger('actual_headcount')->default(0);
            $table->unsignedSmallInteger('new_hires')->default(0);
            $table->unsignedSmallInteger('terminations')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_headcount_plans');
        Schema::dropIfExists('hr_budget_lines');
        Schema::dropIfExists('hr_budget_plans');
    }
};
