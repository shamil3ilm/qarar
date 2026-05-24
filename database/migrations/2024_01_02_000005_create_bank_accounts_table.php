<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            // Bank details
            $table->string('bank_name', 100);
            $table->string('account_name', 100);
            $table->string('account_number', 50);
            $table->string('iban', 50)->nullable();
            $table->string('swift_code', 20)->nullable();
            $table->string('branch_name', 100)->nullable();
            $table->string('branch_code', 20)->nullable();

            // Currency and account type
            $table->string('currency_code', 3);
            $table->enum('account_type', ['current', 'savings', 'credit_card', 'cash'])->default('current');

            // Link to Chart of Accounts
            $table->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            // Current balance (updated via triggers or calculated)
            $table->decimal('current_balance', 18, 4)->default(0);
            $table->date('last_reconciled_date')->nullable();
            $table->decimal('last_reconciled_balance', 18, 4)->nullable();

            $table->decimal('bank_balance', 15, 2)->default(0); // Last known bank balance
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable(); // Bank-specific settings

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'account_number', 'bank_name']);
            $table->index(['organization_id', 'is_active']);
        });

        // bank_transactions created in 2024_01_24_000001_create_bank_reconciliation_tables.php
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
