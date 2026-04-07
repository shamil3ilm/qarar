<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Job postings
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern'])->default('full_time');
            $table->string('location', 200)->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->unsignedInteger('vacancies')->default(1);
            $table->unsignedInteger('filled_count')->default(0);
            $table->enum('status', ['draft', 'open', 'on_hold', 'closed', 'cancelled'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        // Candidates
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 200);
            $table->string('phone', 50)->nullable();
            $table->string('linkedin_url', 500)->nullable();
            $table->string('resume_path', 500)->nullable();
            $table->decimal('total_experience_years', 4, 1)->default(0);
            $table->string('current_company', 200)->nullable();
            $table->string('current_title', 200)->nullable();
            $table->enum('source', ['job_board', 'referral', 'linkedin', 'direct', 'agency', 'other'])->default('direct');
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'email']);
        });

        // Job applications
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->enum('status', [
                'applied',
                'screening',
                'shortlisted',
                'interview_scheduled',
                'interviewed',
                'offer_extended',
                'offer_accepted',
                'offer_declined',
                'hired',
                'rejected',
                'withdrawn',
            ])->default('applied');
            $table->text('cover_letter')->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->unsignedInteger('notice_period_days')->nullable();
            $table->timestamp('applied_at')->useCurrent();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['job_posting_id', 'candidate_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['job_posting_id', 'status']);
        });

        // Interview schedules
        Schema::create('interview_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->enum('interview_type', ['phone', 'video', 'in_person', 'technical', 'panel'])->default('in_person');
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->string('location', 300)->nullable();
            $table->string('meeting_link', 500)->nullable();
            $table->json('interviewers')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->text('feedback')->nullable();
            $table->tinyInteger('rating')->unsigned()->nullable()->comment('1-5');
            $table->enum('recommendation', ['strong_yes', 'yes', 'neutral', 'no', 'strong_no'])->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'job_application_id']);
        });

        // Job offers
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->decimal('offered_salary', 12, 2);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('joining_date')->nullable();
            $table->date('offer_valid_until')->nullable();
            $table->enum('status', ['draft', 'sent', 'accepted', 'declined', 'expired', 'withdrawn'])->default('draft');
            $table->text('terms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'job_application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offers');
        Schema::dropIfExists('interview_schedules');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('job_postings');
    }
};
