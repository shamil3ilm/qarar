<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('workflow_escalation_logs');
        Schema::dropIfExists('workflow_substitution_rules');
        Schema::dropIfExists('workflow_escalation_rules');

        Schema::create('workflow_escalation_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('approval_workflow_id')->nullable()->constrained('approval_workflows')->name('wer_workflow_fk');
            $table->unsignedSmallInteger('step_number')->nullable();
            $table->enum('escalation_type', [
                'reminder',
                'escalate_to_manager',
                'escalate_to_admin',
                'auto_approve',
                'auto_reject',
            ])->default('reminder');
            $table->unsignedSmallInteger('trigger_after_hours');
            $table->foreignId('escalate_to_user_id')->nullable()->constrained('users')->name('wer_escalate_to_fk');
            $table->string('escalate_to_role', 100)->nullable();
            $table->text('notification_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['approval_workflow_id'], 'wer_workflow_idx');
        });

        Schema::create('workflow_escalation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->name('wel_org_fk');
            $table->foreignId('approval_request_id')->constrained('approval_requests')->name('wel_request_fk');
            $table->foreignId('workflow_escalation_rule_id')->constrained('workflow_escalation_rules')->name('wel_rule_fk');
            $table->string('escalation_type', 50);
            $table->dateTime('triggered_at');
            $table->foreignId('escalated_to_user_id')->nullable()->constrained('users')->name('wel_escalated_to_fk');
            $table->string('action_taken', 100)->nullable();
            $table->timestamps();

            $table->index(['approval_request_id'], 'wel_request_idx');
        });

        Schema::create('workflow_substitution_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('wsr_org_fk');
            $table->foreignId('approver_id')->constrained('users')->name('wsr_approver_fk');
            $table->foreignId('substitute_id')->constrained('users')->name('wsr_substitute_fk');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['approver_id', 'is_active'], 'wsr_approver_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_escalation_logs');
        Schema::dropIfExists('workflow_substitution_rules');
        Schema::dropIfExists('workflow_escalation_rules');
    }
};
