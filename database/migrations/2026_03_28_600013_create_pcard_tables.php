<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pcard_transactions');
        Schema::dropIfExists('pcard_statements');
        Schema::dropIfExists('pcards');

        Schema::create('pcards', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('card_number_masked', 20);
            $table->unsignedBigInteger('card_holder_id');
            $table->foreign('card_holder_id', 'pcard_holder_fk')->references('id')->on('users');
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->foreign('cost_center_id', 'pcard_cc_fk')->references('id')->on('cost_centers')->nullOnDelete();
            $table->decimal('credit_limit', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->date('valid_from');
            $table->date('valid_to');
            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
            $table->decimal('single_transaction_limit', 18, 4)->nullable();
            $table->decimal('monthly_limit', 18, 4)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pcard_statements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('pcard_id');
            $table->foreign('pcard_id', 'pcard_stmt_card_fk')->references('id')->on('pcards');
            $table->date('statement_period_start');
            $table->date('statement_period_end');
            $table->decimal('total_amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->enum('status', ['uploaded', 'reconciled', 'posted'])->default('uploaded');
            $table->unsignedBigInteger('uploaded_by');
            $table->foreign('uploaded_by', 'pcard_stmt_usr_fk')->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pcard_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('pcard_statement_id');
            $table->foreign('pcard_statement_id', 'pcard_txn_stmt_fk')->references('id')->on('pcard_statements')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->string('merchant_name');
            $table->string('merchant_category_code', 10)->nullable();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->unsignedBigInteger('gl_account_id')->nullable();
            $table->foreign('gl_account_id', 'pcard_txn_gl_fk')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->foreign('cost_center_id', 'pcard_txn_cc_fk')->references('id')->on('cost_centers')->nullOnDelete();
            $table->enum('status', ['unreconciled', 'reconciled', 'disputed'])->default('unreconciled');
            $table->boolean('receipt_attached')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pcard_transactions');
        Schema::dropIfExists('pcard_statements');
        Schema::dropIfExists('pcards');
    }
};
