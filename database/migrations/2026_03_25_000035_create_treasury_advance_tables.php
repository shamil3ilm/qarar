<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------ //
        // Gap 6: Customer Advance Payment Clearing
        // ------------------------------------------------------------------ //

        if (! Schema::hasTable('advance_payments')) {
            Schema::create('advance_payments', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
                $table->string('advance_number', 30);
                $table->date('advance_date');
                $table->decimal('amount', 15, 4);
                $table->decimal('applied_amount', 15, 4)->default(0);
                $table->decimal('balance_amount', 15, 4)->default(0);
                $table->string('currency_code', 3)->default('SAR');
                $table->string('payment_method', 50)->nullable();
                $table->string('reference', 100)->nullable();
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->enum('status', ['draft', 'received', 'partially_applied', 'fully_applied', 'refunded'])->default('draft');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['organization_id', 'advance_number'], 'ap_org_number_unique');
                $table->index(['organization_id', 'contact_id', 'status'], 'ap_org_contact_status_idx');
            });
        }

        if (! Schema::hasTable('advance_payment_applications')) {
            Schema::create('advance_payment_applications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('advance_payment_id')->constrained('advance_payments')->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->decimal('applied_amount', 15, 4);
                $table->date('applied_date');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
                $table->index(['advance_payment_id'], 'apa_advance_idx');
                $table->index(['invoice_id'], 'apa_invoice_idx');
            });
        }

        // ------------------------------------------------------------------ //
        // Gap 14: Treasury Management
        // ------------------------------------------------------------------ //

        if (! Schema::hasTable('treasury_investments')) {
            Schema::create('treasury_investments', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->string('instrument_number', 30);
                $table->enum('instrument_type', ['fixed_deposit', 'money_market', 'bond', 'treasury_bill', 'mutual_fund'])->default('fixed_deposit');
                $table->string('counterparty', 150);
                $table->decimal('principal_amount', 15, 4);
                $table->decimal('interest_rate', 8, 4);
                $table->date('investment_date');
                $table->date('maturity_date');
                $table->string('currency_code', 3)->default('SAR');
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->decimal('accrued_interest', 15, 4)->default(0);
                $table->decimal('maturity_value', 15, 4)->nullable();
                $table->enum('status', ['active', 'matured', 'pre_liquidated', 'rolled_over'])->default('active');
                $table->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['organization_id', 'instrument_number'], 'ti_org_number_unique');
                $table->index(['organization_id', 'status', 'maturity_date'], 'ti_org_status_maturity_idx');
            });
        }

        if (! Schema::hasTable('bank_positions')) {
            Schema::create('bank_positions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
                $table->date('position_date');
                $table->decimal('book_balance', 15, 4)->default(0);
                $table->decimal('available_balance', 15, 4)->default(0);
                $table->decimal('projected_balance', 15, 4)->default(0);
                $table->string('currency_code', 3)->default('SAR');
                $table->timestamps();
                $table->unique(['organization_id', 'bank_account_id', 'position_date'], 'bp_org_acct_date_unique');
            });
        }

        if (! Schema::hasTable('liquidity_plans')) {
            Schema::create('liquidity_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->string('plan_name', 100);
                $table->date('plan_from');
                $table->date('plan_to');
                $table->enum('granularity', ['daily', 'weekly', 'monthly'])->default('weekly');
                $table->timestamps();
                $table->index(['organization_id'], 'lp_org_idx');
            });
        }

        if (! Schema::hasTable('liquidity_plan_lines')) {
            Schema::create('liquidity_plan_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('liquidity_plan_id')->constrained('liquidity_plans')->cascadeOnDelete();
                $table->date('period_date');
                $table->string('category', 100);
                $table->enum('flow_type', ['inflow', 'outflow'])->default('inflow');
                $table->decimal('planned_amount', 15, 4)->default(0);
                $table->decimal('actual_amount', 15, 4)->default(0);
                $table->string('currency_code', 3)->default('SAR');
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->timestamps();
                $table->index(['liquidity_plan_id', 'period_date'], 'lpl_plan_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidity_plan_lines');
        Schema::dropIfExists('liquidity_plans');
        Schema::dropIfExists('bank_positions');
        Schema::dropIfExists('treasury_investments');
        Schema::dropIfExists('advance_payment_applications');
        Schema::dropIfExists('advance_payments');
    }
};
