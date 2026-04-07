<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * House Bank & Payment Advice — SAP FI-BL (FI12 / FBZP).
 *
 * house_banks          — logical bank entity grouping one or more bank accounts
 * house_bank_accounts  — individual accounts belonging to a house bank (extends bank_accounts)
 * payment_advices      — remittance advice documents sent/received with payments
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('house_banks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 20);                        // e.g. "RIYAD", "SABB"
            $table->string('name', 200);
            $table->string('bank_name', 200)->nullable();
            $table->string('bank_country', 3)->nullable();     // ISO alpha-2/3
            $table->string('swift_code', 11)->nullable();
            $table->string('routing_number', 50)->nullable();
            $table->string('address', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('house_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('house_bank_id');
            $table->unsignedBigInteger('bank_account_id')->nullable(); // optional FK to bank_accounts
            $table->string('account_id_code', 20);              // SAP: account ID within house bank
            $table->string('currency_code', 3)->default('SAR');
            $table->enum('account_purpose', ['payments', 'collections', 'both'])->default('both');
            $table->decimal('daily_payment_limit', 18, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['house_bank_id', 'account_id_code']);
            $table->foreign('house_bank_id')->references('id')->on('house_banks')->cascadeOnDelete();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete();
        });

        Schema::create('payment_advices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('advice_number', 100)->nullable();   // auto-generated
            $table->enum('direction', ['outgoing', 'incoming'])->default('outgoing');
            // Linked payment
            $table->string('payment_type', 50)->nullable();     // payment_received | payment_made
            $table->unsignedBigInteger('payment_id')->nullable();
            // Bank details
            $table->unsignedBigInteger('house_bank_id')->nullable();
            $table->unsignedBigInteger('house_bank_account_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            // Amounts
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('amount', 18, 4);
            $table->date('payment_date');
            $table->string('reference', 200)->nullable();       // bank reference / UTR
            $table->string('narration', 500)->nullable();
            // Status
            $table->enum('status', ['draft', 'sent', 'acknowledged', 'cancelled'])->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'payment_date']);
            $table->index(['organization_id', 'contact_id']);
            $table->index(['organization_id', 'payment_type', 'payment_id'], 'pay_adv_org_pmt_idx');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('house_bank_id')->references('id')->on('house_banks')->nullOnDelete();
            $table->foreign('house_bank_account_id')->references('id')->on('house_bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_advices');
        Schema::dropIfExists('house_bank_accounts');
        Schema::dropIfExists('house_banks');
    }
};
