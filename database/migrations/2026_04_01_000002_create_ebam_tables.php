<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bank account signatories — authorized persons per bank account
        Schema::create('bank_signatories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->enum('authority_level', ['single', 'joint_any', 'joint_all'])->default('single');
            $table->decimal('signing_limit', 18, 4)->nullable();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'bank_account_id']);
            $table->index(['bank_account_id', 'is_active']);
        });

        // Bank account opening / closing / modification requests (eBAM workflow)
        Schema::create('bank_account_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->enum('request_type', ['open', 'close', 'modify', 'add_signatory', 'remove_signatory'])
                ->default('open');
            $table->enum('status', ['pending', 'approved', 'rejected', 'executed'])->default('pending');
            // Fields for 'open' requests
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_type', 30)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('swift_code', 11)->nullable();
            $table->string('branch_name')->nullable();
            // Generic change payload for modify/signatory requests
            $table->json('request_data')->nullable();
            $table->text('justification')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'request_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_account_requests');
        Schema::dropIfExists('bank_signatories');
    }
};
