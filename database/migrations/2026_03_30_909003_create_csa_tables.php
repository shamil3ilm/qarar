<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grc_csa_questionnaires', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('questionnaire_number', 30)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('control_area', ['financial_reporting', 'it_general', 'operational', 'compliance', 'fraud_prevention']);
            $table->date('due_date');
            $table->enum('status', ['draft', 'published', 'in_progress', 'completed', 'reviewed'])->default('draft');
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('grc_csa_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained('grc_csa_questionnaires')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->text('question_text');
            $table->text('guidance')->nullable();
            $table->enum('response_type', ['yes_no', 'rating_1_5', 'text', 'date', 'percentage'])->default('yes_no');
            $table->boolean('is_required')->default(true);
            $table->string('control_objective', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('grc_csa_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained('grc_csa_questionnaires')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('grc_csa_questions')->cascadeOnDelete();
            $table->foreignId('respondent_id')->constrained('users')->restrictOnDelete();
            $table->string('response_value', 500)->nullable();
            $table->text('comments')->nullable();
            $table->boolean('is_effective')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->timestamps();
            $table->unique(['questionnaire_id', 'question_id', 'respondent_id'], 'grc_csa_responses_questionnaire_question_respondent_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grc_csa_responses');
        Schema::dropIfExists('grc_csa_questions');
        Schema::dropIfExists('grc_csa_questionnaires');
    }
};
