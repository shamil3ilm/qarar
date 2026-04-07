<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Projects ──────────────────────────────────────────────────────────
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('project_number');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('project_type', ['internal', 'customer', 'rd', 'capital'])->default('internal');
            $table->foreignId('customer_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->enum('status', ['draft', 'planning', 'active', 'on_hold', 'completed', 'cancelled'])->default('draft');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'project_number']);
            $table->index(['organization_id', 'status']);
        });

        // ── WBS Elements ─────────────────────────────────────────────────────
        Schema::create('wbs_elements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('wbs_elements')->nullOnDelete();
            $table->string('wbs_code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['created', 'released', 'technically_complete', 'closed'])->default('created');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('planned_cost', 15, 2)->default(0);
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->decimal('planned_revenue', 15, 2)->default(0);
            $table->decimal('actual_revenue', 15, 2)->default(0);
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->tinyInteger('progress_percent')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'wbs_code']);
            $table->index(['project_id', 'parent_id']);
        });

        // ── Project Milestones ────────────────────────────────────────────────
        Schema::create('project_milestones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('wbs_element_id')->nullable()->constrained('wbs_elements')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->enum('status', ['pending', 'achieved', 'missed'])->default('pending');
            $table->date('achieved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        // ── Project Time Entries ──────────────────────────────────────────────
        Schema::create('project_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('wbs_element_id')->nullable()->constrained('wbs_elements')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->decimal('hours', 5, 2);
            $table->string('description')->nullable();
            $table->boolean('is_billable')->default(false);
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'employee_id']);
            $table->index(['project_id', 'work_date']);
        });

        // ── Project Cost Entries ──────────────────────────────────────────────
        Schema::create('project_cost_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('wbs_element_id')->nullable()->constrained('wbs_elements')->nullOnDelete();
            $table->enum('cost_type', ['labor', 'material', 'equipment', 'subcontract', 'overhead', 'other'])->default('other');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('cost_date');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'cost_type']);
            $table->index(['reference_type', 'reference_id']);
        });

        // ── Project Members ───────────────────────────────────────────────────
        Schema::create('project_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('role', ['manager', 'member', 'reviewer', 'sponsor'])->default('member');
            $table->date('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('project_cost_entries');
        Schema::dropIfExists('project_time_entries');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('wbs_elements');
        Schema::dropIfExists('projects');
    }
};
