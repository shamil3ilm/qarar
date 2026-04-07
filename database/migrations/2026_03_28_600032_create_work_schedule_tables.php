<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('employee_work_schedules');
        Schema::dropIfExists('work_schedule_rules');
        Schema::dropIfExists('period_work_schedule_days');
        Schema::dropIfExists('period_work_schedules');
        Schema::dropIfExists('daily_work_schedules');

        Schema::create('daily_work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->time('work_start');
            $table->time('work_end');
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->decimal('planned_hours', 4, 2);
            $table->enum('day_type', ['normal', 'reduced', 'off', 'public_holiday'])->default('normal');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('period_work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('cycle_length_weeks')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('period_work_schedule_days', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('period_work_schedule_id');
            $table->foreign('period_work_schedule_id', 'pws_day_pws_fk')->references('id')->on('period_work_schedules')->onDelete('cascade');
            $table->unsignedTinyInteger('week_number');
            $table->tinyInteger('day_of_week'); // 1=Mon, 7=Sun
            $table->unsignedBigInteger('daily_work_schedule_id')->nullable();
            $table->foreign('daily_work_schedule_id', 'pws_day_dws_fk')->references('id')->on('daily_work_schedules')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('work_schedule_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->unsignedBigInteger('period_work_schedule_id');
            $table->foreign('period_work_schedule_id', 'wsr_pws_fk')->references('id')->on('period_work_schedules')->onDelete('restrict');
            $table->date('reference_date');
            $table->decimal('daily_hours', 4, 2);
            $table->decimal('weekly_hours', 5, 2);
            $table->decimal('monthly_hours', 6, 2);
            $table->decimal('overtime_threshold_daily', 4, 2)->nullable();
            $table->decimal('overtime_threshold_weekly', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employee_work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id', 'emp_ws_emp_fk')->references('id')->on('employees')->onDelete('cascade');
            $table->unsignedBigInteger('work_schedule_rule_id');
            $table->foreign('work_schedule_rule_id', 'emp_ws_rule_fk')->references('id')->on('work_schedule_rules')->onDelete('restrict');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_work_schedules');
        Schema::dropIfExists('work_schedule_rules');
        Schema::dropIfExists('period_work_schedule_days');
        Schema::dropIfExists('period_work_schedules');
        Schema::dropIfExists('daily_work_schedules');
    }
};
