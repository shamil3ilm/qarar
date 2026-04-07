<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Appraisal Cycles
        Schema::create('appraisal_cycles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->date('review_period_start');
            $table->date('review_period_end');
            $table->date('self_review_deadline')->nullable();
            $table->date('manager_review_deadline')->nullable();
            $table->enum('status', [
                'draft',
                'active',
                'self_review',
                'manager_review',
                'calibration',
                'completed',
                'cancelled',
            ])->default('draft');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        // Appraisal Templates
        Schema::create('appraisal_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->tinyInteger('rating_scale')->default(5);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        // Appraisal Template Sections
        Schema::create('appraisal_template_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appraisal_template_id')->constrained('appraisal_templates')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight_percent', 5, 2)->default(0);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('appraisal_template_id');
        });

        // Appraisal Template Questions
        Schema::create('appraisal_template_questions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('appraisal_template_section_id');
            $table->foreign('appraisal_template_section_id', 'apprsl_tmpl_questions_section_fk')
                ->references('id')->on('appraisal_template_sections')->cascadeOnDelete();
            $table->text('question');
            $table->enum('question_type', ['rating', 'text', 'yes_no', 'multiselect'])->default('rating');
            $table->boolean('is_required')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('appraisal_template_section_id');
        });

        // Performance Appraisals
        Schema::create('performance_appraisals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('appraisal_cycle_id')->constrained('appraisal_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('appraisal_template_id')->nullable()->constrained('appraisal_templates')->nullOnDelete();
            $table->enum('status', [
                'pending',
                'self_review_submitted',
                'manager_review_submitted',
                'acknowledged',
                'completed',
            ])->default('pending');
            $table->timestamp('self_submitted_at')->nullable();
            $table->timestamp('manager_submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->decimal('overall_self_rating', 3, 2)->nullable();
            $table->decimal('overall_manager_rating', 3, 2)->nullable();
            $table->decimal('final_rating', 3, 2)->nullable();
            $table->text('self_comments')->nullable();
            $table->text('manager_comments')->nullable();
            $table->text('employee_acknowledgement')->nullable();
            $table->timestamps();

            $table->unique(['appraisal_cycle_id', 'employee_id']);
            $table->index(['organization_id', 'status']);
        });

        // Appraisal Responses
        Schema::create('appraisal_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_id')->constrained('performance_appraisals')->cascadeOnDelete();
            $table->foreignId('appraisal_template_question_id')->constrained('appraisal_template_questions')->cascadeOnDelete();
            $table->enum('respondent_type', ['self', 'manager']);
            $table->tinyInteger('rating')->nullable();
            $table->text('text_response')->nullable();
            $table->timestamps();

            $table->index(['performance_appraisal_id', 'respondent_type'], 'appraisal_resp_appraisal_type_idx');
        });

        // Performance Goals
        Schema::create('performance_goals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('appraisal_cycle_id')->nullable()->constrained('appraisal_cycles')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('target_date')->nullable();
            $table->decimal('weight_percent', 5, 2)->default(0);
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->tinyInteger('progress_percent')->default(0);
            $table->tinyInteger('self_rating')->nullable();
            $table->tinyInteger('manager_rating')->nullable();
            $table->text('self_comments')->nullable();
            $table->text('manager_comments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'employee_id', 'status']);
        });

        // Performance Goal Updates
        Schema::create('performance_goal_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_goal_id')->constrained('performance_goals')->cascadeOnDelete();
            $table->foreignId('updated_by')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('progress_percent');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('performance_goal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_goal_updates');
        Schema::dropIfExists('performance_goals');
        Schema::dropIfExists('appraisal_responses');
        Schema::dropIfExists('performance_appraisals');
        Schema::dropIfExists('appraisal_template_questions');
        Schema::dropIfExists('appraisal_template_sections');
        Schema::dropIfExists('appraisal_templates');
        Schema::dropIfExists('appraisal_cycles');
    }
};
