<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gdpr_consent_records');
        Schema::dropIfExists('gdpr_processing_activities');
        Schema::dropIfExists('gdpr_data_subject_requests');

        Schema::create('gdpr_data_subject_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->enum('request_type', ['access', 'erasure', 'portability', 'rectification', 'restriction', 'objection']);
            $table->string('requester_name');
            $table->string('requester_email');
            $table->unsignedBigInteger('requester_id')->nullable();
            $table->enum('status', ['received', 'verifying', 'processing', 'completed', 'rejected'])->default('received');
            $table->timestamp('received_at');
            $table->timestamp('deadline_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('data_exported_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('requester_id', 'gdpr_req_usr_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('gdpr_processing_activities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('activity_name');
            $table->text('purpose');
            $table->enum('legal_basis', ['consent', 'contract', 'legal_obligation', 'vital_interests', 'public_task', 'legitimate_interests']);
            $table->json('data_categories');
            $table->json('recipient_categories')->nullable();
            $table->unsignedInteger('retention_period_days')->nullable();
            $table->boolean('third_country_transfers')->default(false);
            $table->boolean('dpia_required')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('gdpr_consent_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('purpose');
            $table->boolean('consent_given')->default(false);
            $table->timestamp('given_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('consent_text')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('contact_id', 'gdpr_con_contact_fk')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('user_id', 'gdpr_con_usr_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_consent_records');
        Schema::dropIfExists('gdpr_processing_activities');
        Schema::dropIfExists('gdpr_data_subject_requests');
    }
};
