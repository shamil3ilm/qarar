<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_patterns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->json('days_of_week');
            $table->boolean('crosses_midnight')->default(false);
            $table->string('color_hex', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active'], 'shft_pat_org_active_idx');
        });

        Schema::create('shift_rosters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->date('roster_period_start');
            $table->date('roster_period_end');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'shft_ros_org_status_idx');
            $table->index(['organization_id', 'roster_period_start', 'roster_period_end'], 'shft_ros_org_period_idx');
        });

        Schema::create('shift_roster_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('roster_id')->constrained('shift_rosters')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_pattern_id')->nullable()->constrained('shift_patterns')->nullOnDelete();
            $table->date('shift_date');
            $table->boolean('is_day_off')->default(false);
            $table->time('override_start_time')->nullable();
            $table->time('override_end_time')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['roster_id', 'employee_id', 'shift_date'], 'shft_line_ros_emp_date_uniq');
            $table->index(['employee_id', 'shift_date'], 'shft_line_emp_date_idx');
        });

        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('requested_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('requester_roster_line_id')->nullable()->constrained('shift_roster_lines')->nullOnDelete();
            $table->foreignId('requested_roster_line_id')->nullable()->constrained('shift_roster_lines')->nullOnDelete();
            $table->date('requester_shift_date');
            $table->date('requested_shift_date');
            $table->string('reason', 500)->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'approved', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'shft_swp_org_status_idx');
            $table->index(['requester_id', 'status'], 'shft_swp_req_status_idx');
            $table->index(['requested_employee_id', 'status'], 'shft_swp_reqd_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_swap_requests');
        Schema::dropIfExists('shift_roster_lines');
        Schema::dropIfExists('shift_rosters');
        Schema::dropIfExists('shift_patterns');
    }
};
