<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_positions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('title');
            $table->enum('criticality', ['critical', 'high', 'medium'])->default('high');
            $table->foreignId('current_holder_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('target_fill_date')->nullable();
            $table->unsignedSmallInteger('min_successors')->default(2);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'criticality', 'is_active'], 'key_pos_org_crit_active_idx');
            $table->index(['organization_id', 'department_id'], 'key_pos_org_dept_idx');
        });

        Schema::create('succession_candidates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('key_position_id')->constrained('key_positions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('readiness', ['ready_now', 'one_two_years', 'three_five_years'])->default('three_five_years');
            $table->unsignedTinyInteger('performance_rating')->nullable()->comment('1-5 rating');
            $table->unsignedTinyInteger('potential_rating')->nullable()->comment('1-5 rating');
            $table->foreignId('nominated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('nomination_date')->nullable();
            $table->date('last_reviewed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['key_position_id', 'employee_id'], 'succ_cand_pos_emp_uniq');
            $table->index(['employee_id', 'readiness'], 'succ_cand_emp_ready_idx');
            $table->index(['key_position_id', 'readiness'], 'succ_cand_pos_ready_idx');
        });

        Schema::create('succession_pool_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('candidate_id')->constrained('succession_candidates')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('activity_type', 50);
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('target_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->text('outcome')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['candidate_id', 'status'], 'succ_act_cand_status_idx');
            $table->index(['employee_id', 'status'], 'succ_act_emp_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('succession_pool_activities');
        Schema::dropIfExists('succession_candidates');
        Schema::dropIfExists('key_positions');
    }
};
