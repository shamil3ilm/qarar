<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment Tolerance & Clearing Variance — SAP FI OBA3/OBB8 equivalent.
 *
 * payment_tolerance_groups — named tolerance bands per org (e.g. "Default", "Key Accounts")
 * payment_tolerance_items  — per-currency thresholds (absolute + percentage)
 * payment_difference_posts — audit trail of written-off / credited tolerance variances
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_tolerance_groups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 20);                        // e.g. "DEFAULT", "KEY-ACC"
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->enum('applies_to', ['customer', 'supplier', 'both'])->default('both');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('payment_tolerance_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tolerance_group_id');
            $table->string('currency_code', 3);               // ISO 4217
            // Underpayment tolerance (customer pays less than invoice)
            $table->decimal('underpay_abs', 18, 4)->default(0);    // absolute max underpayment
            $table->decimal('underpay_pct', 7, 4)->default(0);     // % of invoice amount
            // Overpayment tolerance (customer pays more)
            $table->decimal('overpay_abs', 18, 4)->default(0);
            $table->decimal('overpay_pct', 7, 4)->default(0);
            // GL accounts for automatic write-off postings
            $table->unsignedBigInteger('underpay_gl_account_id')->nullable();  // expense
            $table->unsignedBigInteger('overpay_gl_account_id')->nullable();   // income
            $table->timestamps();

            $table->unique(['tolerance_group_id', 'currency_code']);
            $table->foreign('tolerance_group_id')->references('id')->on('payment_tolerance_groups')->cascadeOnDelete();
            $table->foreign('underpay_gl_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('overpay_gl_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        Schema::create('payment_difference_posts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('tolerance_group_id');
            // Source (the payment being cleared)
            $table->string('payment_type', 50);               // payment_received | payment_made
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            // Cleared document (invoice / bill)
            $table->string('document_type', 50)->nullable();  // invoice | bill | credit_note
            $table->unsignedBigInteger('document_id')->nullable();
            // Amounts
            $table->string('currency_code', 3);
            $table->decimal('invoice_amount', 18, 4);
            $table->decimal('payment_amount', 18, 4);
            $table->decimal('difference_amount', 18, 4);      // signed: negative = underpay
            $table->enum('difference_type', ['underpayment', 'overpayment']);
            // Resolution
            $table->enum('resolution', ['written_off', 'credited', 'auto_cleared'])->default('written_off');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->date('posting_date');
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'payment_type', 'payment_id'], 'pay_diff_posts_org_type_id_idx');
            $table->index(['organization_id', 'posting_date']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('tolerance_group_id')->references('id')->on('payment_tolerance_groups');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_difference_posts');
        Schema::dropIfExists('payment_tolerance_items');
        Schema::dropIfExists('payment_tolerance_groups');
    }
};
