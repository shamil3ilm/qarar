<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_center_capacities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('work_center_id')->constrained('work_centers')->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->decimal('available_hours_per_day', 8, 2)->comment('Raw capacity in hours per working day');
            $table->unsignedTinyInteger('days_per_week')->default(5)->comment('Number of working days per week (1-7)');
            $table->decimal('efficiency_pct', 5, 2)->default(100.00)->comment('Percentage of time that is actually productive');
            $table->timestamps();

            $table->index(['organization_id', 'work_center_id', 'valid_from'], 'work_center_capacities_org_work_center_valid_from_idx');
        });

        Schema::create('mrp_capacity_requirements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('mrp_run_id')->nullable()->comment('FK to mrp_runs if present');
            $table->foreignId('work_center_id')->constrained('work_centers')->cascadeOnDelete();
            $table->unsignedBigInteger('planned_order_id')->nullable()->comment('Links to mrp_planned_orders');
            $table->date('required_date');
            $table->decimal('required_hours', 10, 2);
            $table->decimal('available_hours', 10, 2);
            $table->decimal('load_pct', 6, 2)->comment('required_hours / available_hours * 100');
            $table->enum('status', ['feasible', 'overloaded'])->default('feasible');
            $table->timestamps();

            $table->index(['organization_id', 'work_center_id', 'required_date'], 'mrp_capacity_requirements_org_work_center_req_date_idx');
            $table->index(['mrp_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mrp_capacity_requirements');
        Schema::dropIfExists('work_center_capacities');
    }
};
