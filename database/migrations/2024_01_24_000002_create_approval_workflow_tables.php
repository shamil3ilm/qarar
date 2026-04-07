<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Approval workflow definitions
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('approvable_type', 100)->nullable(); // App\Models\Sales\Invoice, etc.
            $table->decimal('min_amount', 15, 4)->nullable();
            $table->decimal('max_amount', 15, 4)->nullable();
            $table->json('conditions')->nullable();
            $table->unsignedTinyInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'approvable_type', 'is_active'], 'approval_wf_org_approvable_active_idx');
        });

        // Workflow steps (approval chain)
        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sequence')->default(1);
            $table->string('approver_type', 30); // user, role, department_head, reporting_manager, custom
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->json('approver_custom')->nullable();
            $table->string('action_type', 30)->nullable();
            $table->json('condition')->nullable();
            $table->json('conditions')->nullable();
            $table->boolean('requires_all')->default(false);
            $table->unsignedInteger('min_approvers')->default(1);
            $table->unsignedInteger('timeout_hours')->nullable();
            $table->boolean('can_skip')->default(false);
            $table->boolean('can_delegate')->default(false);
            $table->timestamps();

            $table->index(['approval_workflow_id', 'sequence']);
        });

        // Approval requests
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->nullableMorphs('approvable');
            $table->foreignId('current_step_id')->nullable()->constrained('approval_workflow_steps')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->decimal('amount', 15, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        // Individual approval actions
        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained('approval_workflow_steps')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('delegated_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delegated_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('action_at')->nullable();
            $table->foreignId('action_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamps();

            $table->index(['approval_request_id', 'workflow_step_id']);
            $table->index(['assigned_to', 'status']);
        });

        // Approval delegates (temporary delegation)
        Schema::create('approval_delegates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->json('entity_types')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['delegator_id', 'is_active']);
            $table->index(['delegate_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegates');
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
