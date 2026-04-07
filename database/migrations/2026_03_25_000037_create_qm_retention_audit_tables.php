<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // Gap 13: Certificates of Analysis
        // ---------------------------------------------------------------
        Schema::create('certificates_of_analysis', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('certificate_number', 30);
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('batch_number', 100)->nullable();
            $table->unsignedBigInteger('inspection_lot_id')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->date('issue_date');
            $table->date('test_date')->nullable();
            $table->json('test_results');
            $table->enum('overall_result', ['pass', 'fail', 'conditional'])->default('pass');
            $table->text('remarks')->nullable();
            $table->foreignId('issued_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'approved', 'issued', 'revoked'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'certificate_number'], 'coa_org_number_unique');
            $table->index(['organization_id', 'product_id', 'status'], 'coa_org_product_status_idx');
        });

        // ---------------------------------------------------------------
        // Gap 15: Document Retention Policies
        // ---------------------------------------------------------------
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('policy_name', 100);
            $table->integer('retention_years');
            $table->enum('jurisdiction', ['saudi_arabia', 'uae', 'india', 'global'])->default('global');
            $table->enum('action_on_expiry', ['archive', 'delete', 'notify_only'])->default('notify_only');
            $table->boolean('legal_hold_override')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'document_type', 'jurisdiction'], 'rp_org_type_jurisdiction_unique');
        });

        Schema::create('document_legal_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->unsignedBigInteger('document_id');
            $table->string('hold_reason', 500);
            $table->foreignId('held_by')->constrained('users');
            $table->date('hold_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['document_type', 'document_id'], 'dlh_doc_idx');
            $table->index(['organization_id', 'is_active'], 'dlh_org_active_idx');
        });

        Schema::create('retention_schedule_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->timestamp('run_at');
            $table->integer('documents_evaluated')->default(0);
            $table->integer('documents_archived')->default(0);
            $table->integer('documents_deleted')->default(0);
            $table->integer('documents_skipped_legal_hold')->default(0);
            $table->text('run_log')->nullable();
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // Gap 20: Sensitive Access Logs
        // ---------------------------------------------------------------
        Schema::create('sensitive_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('model_type', 100);
            $table->unsignedBigInteger('model_id');
            $table->string('action', 20)->default('read');
            $table->string('sensitive_fields', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id'], 'sal_model_idx');
            $table->index(['user_id', 'created_at'], 'sal_user_date_idx');
            $table->index(['organization_id', 'created_at'], 'sal_org_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_access_logs');
        Schema::dropIfExists('retention_schedule_runs');
        Schema::dropIfExists('document_legal_holds');
        Schema::dropIfExists('retention_policies');
        Schema::dropIfExists('certificates_of_analysis');
    }
};
