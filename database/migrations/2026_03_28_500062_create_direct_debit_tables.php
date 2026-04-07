<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('direct_debit_collections');
        Schema::dropIfExists('direct_debit_mandates');

        Schema::create('direct_debit_mandates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('mandate_reference', 50);
            $table->enum('mandate_type', ['core', 'b2b', 'standing_order'])->default('core');
            $table->enum('direction', ['collection', 'payment'])->default('collection');
            $table->foreignId('counterparty_id')->constrained('contacts')->name('ddm_counterparty_fk');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->name('ddm_bank_account_fk');
            $table->string('iban', 34)->nullable();
            $table->string('bic', 11)->nullable();
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('amount', 18, 4)->nullable();
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly', 'quarterly', 'annually', 'one_time'])
                ->default('monthly');
            $table->date('first_collection_date')->nullable();
            $table->date('next_collection_date')->nullable();
            $table->date('last_collection_date')->nullable();
            $table->unsignedInteger('total_collections')->default(0);
            $table->unsignedInteger('max_collections')->nullable();
            $table->enum('status', ['draft', 'active', 'paused', 'cancelled', 'expired'])->default('draft');
            $table->date('signed_date')->nullable();
            $table->date('cancellation_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'mandate_reference'], 'ddm_org_ref_unq');
            $table->index(['organization_id', 'status', 'next_collection_date'], 'ddm_org_status_next_idx');
        });

        Schema::create('direct_debit_collections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('ddc_org_fk');
            $table->foreignId('direct_debit_mandate_id')->constrained('direct_debit_mandates')->name('ddc_mandate_fk');
            $table->date('collection_date');
            $table->decimal('amount', 18, 4);
            $table->enum('status', ['scheduled', 'submitted', 'collected', 'failed', 'returned'])->default('scheduled');
            $table->foreignId('payment_run_id')->nullable()->constrained('payment_runs')->name('ddc_payment_run_fk');
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['direct_debit_mandate_id'], 'ddc_mandate_idx');
            $table->index(['collection_date', 'status'], 'ddc_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_debit_collections');
        Schema::dropIfExists('direct_debit_mandates');
    }
};
