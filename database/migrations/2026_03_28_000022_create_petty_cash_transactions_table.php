<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('fund_id')->constrained('petty_cash_funds')->cascadeOnDelete();
            $table->string('transaction_number', 50)->nullable();
            $table->date('transaction_date');
            $table->enum('transaction_type', ['receipt', 'payment', 'replenishment'])->default('payment');
            $table->decimal('amount', 15, 2);
            $table->string('description', 500)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('payee_payer', 200)->nullable();
            $table->string('receipt_reference', 100)->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'posted', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'fund_id', 'transaction_date'], 'pct_org_fund_date_idx');
            $table->index(['organization_id', 'status'], 'pct_org_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_transactions');
    }
};
