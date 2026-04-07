<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grc_audit_engagements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('engagement_number', 30)->unique();
            $table->string('title', 200);
            $table->enum('audit_type', ['internal', 'external', 'regulatory', 'it', 'operational', 'financial', 'compliance']);
            $table->date('planned_start_date');
            $table->date('planned_end_date');
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->enum('status', ['planning', 'fieldwork', 'review', 'issued', 'closed'])->default('planning');
            $table->text('scope')->nullable();
            $table->text('objectives')->nullable();
            $table->foreignId('lead_auditor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('grc_audit_findings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('engagement_id')->nullable()->constrained('grc_audit_engagements')->nullOnDelete();
            $table->string('finding_number', 30)->unique();
            $table->string('title', 200);
            $table->text('description');
            $table->text('criteria')->nullable();
            $table->text('condition')->nullable();
            $table->text('cause')->nullable();
            $table->text('effect')->nullable();
            $table->text('recommendation')->nullable();
            $table->enum('severity', ['critical', 'high', 'medium', 'low', 'informational'])->default('medium');
            $table->enum('status', ['open', 'assigned', 'in_remediation', 'remediated', 'verified', 'closed', 'risk_accepted'])->default('open');
            $table->enum('finding_type', ['control_deficiency', 'process_gap', 'policy_violation', 'fraud_risk', 'it_risk', 'compliance_gap']);
            $table->string('module_reference', 50)->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('management_response')->nullable();
            $table->date('management_response_date')->nullable();
            $table->text('remediation_plan')->nullable();
            $table->date('remediation_target_date')->nullable();
            $table->date('remediation_completed_date')->nullable();
            $table->text('verification_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('repeat_finding')->default(false);
            $table->unsignedBigInteger('parent_finding_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'severity']);
            $table->index(['organization_id', 'due_date']);
        });

        Schema::create('grc_finding_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finding_id')->constrained('grc_audit_findings')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date');
            $table->enum('status', ['open', 'in_progress', 'completed', 'overdue'])->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['finding_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grc_finding_actions');
        Schema::dropIfExists('grc_audit_findings');
        Schema::dropIfExists('grc_audit_engagements');
    }
};
