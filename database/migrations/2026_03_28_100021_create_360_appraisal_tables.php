<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('appraisal_reviewer_responses');
        Schema::dropIfExists('appraisal_reviewers');

        Schema::create('appraisal_reviewers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appraisal_id')
                ->constrained('performance_appraisals')
                ->cascadeOnDelete();
            $table->foreignId('reviewer_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->string('reviewer_type', 50)
                ->comment('self, peer, subordinate, manager, external');
            $table->string('status', 50)->default('pending')
                ->comment('pending, in_progress, submitted, declined');
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('overall_rating', 3, 2)->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('comments')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->date('due_date')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['appraisal_id', 'status']);
            $table->index(['reviewer_id', 'status']);
            // One reviewer can only appear once per appraisal per type
            $table->unique(['appraisal_id', 'reviewer_id', 'reviewer_type'], 'appr_reviewer_appraisal_reviewer_type_unq');
        });

        Schema::create('appraisal_reviewer_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appraisal_reviewer_id')
                ->constrained('appraisal_reviewers')
                ->cascadeOnDelete();
            $table->foreignId('question_id')
                ->nullable()
                ->constrained('appraisal_template_questions')
                ->nullOnDelete();
            $table->string('question_text')
                ->comment('Denormalised in case the template changes after submission');
            $table->decimal('rating', 3, 2)->nullable();
            $table->text('response_text')->nullable();
            $table->timestamps();

            $table->index('appraisal_reviewer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_reviewer_responses');
        Schema::dropIfExists('appraisal_reviewers');
    }
};
