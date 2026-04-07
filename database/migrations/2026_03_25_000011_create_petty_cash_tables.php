<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_funds', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name', 100);
            $table->foreignId('custodian_id')->constrained('users');
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->decimal('opening_balance', 15, 4)->default(0);
            $table->decimal('current_balance', 15, 4)->default(0);
            $table->decimal('max_transaction_limit', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active'], 'petty_cash_funds_org_active_idx');
            $table->index(['organization_id', 'branch_id'], 'petty_cash_funds_org_branch_idx');
        });

        Schema::create('petty_cash_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('fund_id')->constrained('petty_cash_funds')->cascadeOnDelete();
            $table->string('voucher_number', 30)->unique();
            $table->date('voucher_date');
            $table->enum('transaction_type', ['receipt', 'payment']);
            $table->decimal('amount', 15, 4);
            $table->string('description', 500);
            $table->string('category', 100)->nullable();
            $table->string('payee_payer', 200)->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'approved', 'posted', 'cancelled'])->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fund_id', 'voucher_date'], 'petty_cash_vouchers_fund_date_idx');
            $table->index(['fund_id', 'status'], 'petty_cash_vouchers_fund_status_idx');
        });

        Schema::create('petty_cash_replenishments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('fund_id')->constrained('petty_cash_funds')->cascadeOnDelete();
            $table->date('replenishment_date');
            $table->decimal('amount', 15, 4);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['requested', 'approved', 'disbursed'])->default('requested');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fund_id', 'status'], 'petty_cash_replen_fund_status_idx');
            $table->index(['fund_id', 'replenishment_date'], 'petty_cash_replen_fund_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_replenishments');
        Schema::dropIfExists('petty_cash_vouchers');
        Schema::dropIfExists('petty_cash_funds');
    }
};
