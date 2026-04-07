<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('email', 150);
            $table->string('password_hash', 255);
            $table->boolean('is_active')->default(true);
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->integer('login_count')->default(0);
            $table->string('password_reset_token', 100)->nullable();
            $table->dateTime('password_reset_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'email'], 'portal_users_org_email_unique');
            $table->index(['contact_id', 'is_active'], 'portal_users_contact_active_idx');
        });

        Schema::create('portal_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('session_token', 255);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('last_activity_at');
            $table->timestamps();

            $table->index('session_token', 'portal_sessions_token_idx');
            $table->index('portal_user_id', 'portal_sessions_user_idx');
        });

        Schema::create('portal_document_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('document_type', 30); // invoice|quotation|order|credit_note|statement
            $table->unsignedBigInteger('document_id');
            $table->dateTime('accessed_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['portal_user_id', 'document_type'], 'portal_doc_acc_user_type_idx');
        });

        Schema::create('portal_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('activity_type', 50);
            $table->string('description', 200);
            $table->json('metadata')->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_activity_logs');
        Schema::dropIfExists('portal_document_accesses');
        Schema::dropIfExists('portal_sessions');
        Schema::dropIfExists('portal_users');
    }
};
