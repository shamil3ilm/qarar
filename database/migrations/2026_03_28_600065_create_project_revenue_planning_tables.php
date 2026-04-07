<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('project_revenue_plan_lines');
        Schema::dropIfExists('project_revenue_plans');

        Schema::create('project_revenue_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('version', 10)->default('0');
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->decimal('total_planned_revenue', 18, 4)->default(0);
            $table->decimal('total_planned_cost', 18, 4)->default(0);
            $table->char('currency', 3);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('approved_by', 'proj_rev_plan_usr_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('project_revenue_plan_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_revenue_plan_id');
            $table->tinyInteger('period_month');
            $table->decimal('planned_revenue', 18, 4)->default(0);
            $table->decimal('planned_cost', 18, 4)->default(0);
            $table->decimal('actual_revenue', 18, 4)->default(0);
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->timestamps();

            $table->foreign('project_revenue_plan_id', 'proj_rev_plan_line_fk')->references('id')->on('project_revenue_plans')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_revenue_plan_lines');
        Schema::dropIfExists('project_revenue_plans');
    }
};
