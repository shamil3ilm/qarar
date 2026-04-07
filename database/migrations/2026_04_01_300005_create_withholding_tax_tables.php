<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Withholding Tax (WHT) — SAP F.67/F.68 equivalent.
 *
 * withholding_tax_codes  — master codes (rate, applicable_to, GL accounts)
 * withholding_tax_lines  — WHT deducted / collected on individual payments
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withholding_tax_codes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 20);                       // e.g. WHT001, W1
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->enum('applicable_to', ['supplier', 'customer', 'both'])->default('supplier');
            $table->decimal('rate', 7, 4);                    // 5.0000 = 5%
            $table->string('country_code', 3)->nullable();    // ISO-3166 alpha-3 or alpha-2
            $table->string('tax_type', 50)->nullable();       // e.g. WHT, TCS, royalty
            $table->decimal('threshold_amount', 18, 4)->nullable(); // minimum cumulative before WHT kicks in
            $table->decimal('ceiling_amount', 18, 4)->nullable();   // max cumulative WHT per period
            // GL accounts
            $table->unsignedBigInteger('payable_account_id')->nullable();   // Dr Expense / Cr WHT Payable
            $table->unsignedBigInteger('receivable_account_id')->nullable(); // for customer-side (TCS)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('payable_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('receivable_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        Schema::create('withholding_tax_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('wht_code_id');
            // Polymorphic link to the parent payment (payments_received or payments_made)
            $table->string('payment_type', 50);               // 'payment_received' | 'payment_made'
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->decimal('gross_amount', 18, 4);           // taxable payment amount
            $table->decimal('wht_rate', 7, 4);                // rate applied (snapshot)
            $table->decimal('wht_amount', 18, 4);             // computed WHT
            $table->decimal('net_amount', 18, 4);             // gross - wht
            $table->string('currency_code', 3)->default('SAR');
            $table->date('transaction_date');
            $table->string('certificate_number', 100)->nullable(); // WHT certificate issued
            $table->date('certificate_date')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'payment_type', 'payment_id'], 'wht_lines_org_payment_idx');
            $table->index(['organization_id', 'contact_id'], 'wht_lines_org_contact_idx');
            $table->index(['organization_id', 'transaction_date'], 'wht_lines_org_date_idx');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('wht_code_id')->references('id')->on('withholding_tax_codes')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withholding_tax_lines');
        Schema::dropIfExists('withholding_tax_codes');
    }
};
