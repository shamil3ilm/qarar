<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HCM Onboarding — structured task checklists for new employee onboarding.
 *
 * SAP HCM equivalent: Onboarding Cockpit (PA40 trigger → task assignment).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_onboardings', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('template_type', 50)->default('standard')
                ->comment('standard | probation | rehire | transfer_in');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->date('started_date');
            $table->date('target_completion_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('hr_onboarding_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('hr_onboardings')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('category', 50)->default('hr')
                ->comment('hr | it | manager | employee | legal | finance');
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'done', 'skipped'])->default('pending');
            $table->boolean('is_required')->default(true);
            $table->tinyInteger('sort_order')->unsigned()->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['onboarding_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_onboarding_tasks');
        Schema::dropIfExists('hr_onboardings');
    }
};
