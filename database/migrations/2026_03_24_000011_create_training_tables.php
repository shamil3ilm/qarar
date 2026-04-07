<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_providers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('training_courses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('training_providers')->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', [
                'technical',
                'soft_skills',
                'compliance',
                'safety',
                'leadership',
                'onboarding',
                'other',
            ])->default('other');
            $table->enum('delivery_type', [
                'in_person',
                'online',
                'blended',
                'self_paced',
            ])->default('in_person');
            $table->decimal('duration_hours', 5, 1)->default(1);
            $table->unsignedInteger('max_participants')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->unsignedInteger('validity_months')->nullable()->comment('Months before recertification needed');
            $table->decimal('cost_per_participant', 10, 2)->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
        });

        Schema::create('training_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->string('session_number');
            $table->string('trainer_name')->nullable();
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->unsignedInteger('max_participants')->nullable();
            $table->unsignedInteger('enrolled_count')->default(0);
            $table->enum('status', [
                'scheduled',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('scheduled');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['course_id', 'status']);
        });

        Schema::create('training_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('status', [
                'enrolled',
                'attended',
                'completed',
                'failed',
                'cancelled',
                'no_show',
            ])->default('enrolled');
            $table->timestamp('enrolled_at');
            $table->date('completion_date')->nullable();
            $table->decimal('score', 5, 2)->nullable()->comment('Percentage');
            $table->text('feedback')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'employee_id']);
            $table->index(['employee_id', 'status']);
            $table->index('organization_id');
        });

        Schema::create('training_certifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('training_enrollments')->nullOnDelete();
            $table->string('certificate_number')->nullable();
            $table->date('issued_date');
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('issued_by')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'course_id']);
            $table->index('organization_id');
        });

        Schema::create('training_needs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete()->comment('null = department-wide');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('training_courses')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['identified', 'planned', 'fulfilled', 'cancelled'])->default('identified');
            $table->foreignId('identified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('target_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_needs');
        Schema::dropIfExists('training_certifications');
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_courses');
        Schema::dropIfExists('training_providers');
    }
};
