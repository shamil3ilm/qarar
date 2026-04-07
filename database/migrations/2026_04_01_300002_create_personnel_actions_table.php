<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('action_number')->unique();             // PA-2026-00001 (SAP PA40 action number)
            $table->unsignedBigInteger('employee_id');
            $table->string('action_type');                        // hire|transfer|promotion|demotion|exit|rehire|leave_of_absence
            $table->date('effective_date');
            $table->string('status')->default('draft');           // draft|submitted|approved|completed|reversed|rejected
            $table->json('payload')->nullable();                  // action-specific data (new dept, new salary, etc.)
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->unsignedBigInteger('initiated_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'employee_id']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('personnel_action_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('personnel_action_id');
            $table->string('step_name');                          // e.g. update_position, update_salary, notify_payroll
            $table->string('status')->default('pending');         // pending|completed|failed|skipped
            $table->json('result')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->foreign('personnel_action_id')->references('id')->on('personnel_actions')->cascadeOnDelete();
            $table->index(['personnel_action_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_action_steps');
        Schema::dropIfExists('personnel_actions');
    }
};
