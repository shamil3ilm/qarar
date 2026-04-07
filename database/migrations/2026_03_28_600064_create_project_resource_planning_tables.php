<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('project_time_sheets');
        Schema::dropIfExists('project_resource_plans');

        Schema::create('project_resource_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->string('wbs_element')->nullable();
            $table->enum('resource_type', ['labor', 'equipment', 'material', 'subcontractor']);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('resource_description');
            $table->decimal('planned_quantity', 10, 2);
            $table->string('uom', 20);
            $table->date('planned_start');
            $table->date('planned_end');
            $table->decimal('cost_rate', 18, 4)->nullable();
            $table->decimal('planned_cost', 18, 4)->nullable();
            $table->decimal('actual_quantity', 10, 2)->default(0);
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('project_time_sheets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('project_id');
            $table->string('wbs_element')->nullable();
            $table->date('work_date');
            $table->decimal('hours_worked', 5, 2);
            $table->text('activity_description')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('employee_id', 'proj_ts_emp_fk')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('approved_by', 'proj_ts_appr_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_time_sheets');
        Schema::dropIfExists('project_resource_plans');
    }
};
