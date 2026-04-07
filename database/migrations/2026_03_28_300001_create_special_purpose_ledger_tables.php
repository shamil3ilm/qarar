<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('special_ledger_mapping_rules');
        Schema::dropIfExists('special_ledger_entries');
        Schema::dropIfExists('special_ledgers');

        Schema::create('special_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('accounting_principle', 50)->default('ifrs');
            $table->boolean('is_leading')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('currency_code', 3)->default('SAR');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'fk_sl_org')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unique(['organization_id', 'code'], 'uq_sl_org_code');
            $table->index(['organization_id', 'is_active'], 'idx_sl_org_active');
        });

        Schema::create('special_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('special_ledger_id');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('account_id');
            $table->date('posting_date');
            $table->decimal('amount', 18, 4);
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('amount_local', 18, 4);
            $table->char('debit_credit', 1);
            $table->tinyInteger('period');
            $table->smallInteger('fiscal_year');
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('profit_center_id')->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            $table->foreign('special_ledger_id', 'fk_sle_ledger')
                ->references('id')->on('special_ledgers')->onDelete('cascade');
            $table->foreign('journal_entry_id', 'fk_sle_je')
                ->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('account_id', 'fk_sle_account')
                ->references('id')->on('chart_of_accounts')->onDelete('restrict');

            $table->index(['special_ledger_id', 'fiscal_year', 'period'], 'idx_sle_ledger_period');
            $table->index(['account_id', 'posting_date'], 'idx_sle_account_date');
            $table->index('organization_id', 'idx_sle_org');
        });

        Schema::create('special_ledger_mapping_rules', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('special_ledger_id');
            $table->unsignedBigInteger('source_account_id')->nullable();
            $table->string('account_type', 50)->nullable();
            $table->unsignedBigInteger('target_account_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('special_ledger_id', 'fk_slmr_ledger')
                ->references('id')->on('special_ledgers')->onDelete('cascade');
            $table->foreign('source_account_id', 'fk_slmr_src_acct')
                ->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('target_account_id', 'fk_slmr_tgt_acct')
                ->references('id')->on('chart_of_accounts')->onDelete('set null');

            $table->index(['organization_id', 'is_active'], 'idx_slmr_org_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_ledger_mapping_rules');
        Schema::dropIfExists('special_ledger_entries');
        Schema::dropIfExists('special_ledgers');
    }
};
