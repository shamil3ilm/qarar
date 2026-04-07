<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IFRS 16 / ASC 842 lease contracts
        Schema::create('lease_contracts', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            $table->string('lease_number', 50);
            $table->unique(['organization_id', 'lease_number']);

            // Lease parties
            $table->enum('party_role', ['lessee', 'lessor'])->default('lessee');
            $table->string('asset_description');
            $table->string('lessor_name')->nullable();
            $table->string('lessor_contact', 200)->nullable();

            // Dates & terms
            $table->date('commencement_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('lease_term_months');

            // Payment terms
            $table->decimal('payment_amount', 18, 4);
            $table->enum('payment_frequency', ['monthly', 'quarterly', 'semi_annual', 'annual'])->default('monthly');
            $table->string('currency_code', 3)->default('SAR');

            // Discount rate (IBR or implicit rate)
            $table->decimal('discount_rate', 10, 6)->comment('Annual discount rate, e.g. 0.05 for 5%');

            // IFRS 16 classification
            $table->enum('classification', ['finance', 'operating', 'short_term', 'low_value'])->default('finance');

            // Calculated present values (set on creation)
            $table->decimal('initial_rou_asset', 18, 4)->default(0);
            $table->decimal('initial_lease_liability', 18, 4)->default(0);
            $table->decimal('current_lease_liability', 18, 4)->default(0);

            // GL account linkage
            $table->unsignedBigInteger('rou_asset_account_id')->nullable();
            $table->foreign('rou_asset_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->unsignedBigInteger('accum_depreciation_account_id')->nullable();
            $table->foreign('accum_depreciation_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->unsignedBigInteger('lease_liability_account_id')->nullable();
            $table->foreign('lease_liability_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->unsignedBigInteger('interest_expense_account_id')->nullable();
            $table->foreign('interest_expense_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->unsignedBigInteger('depreciation_expense_account_id')->nullable();
            $table->foreign('depreciation_expense_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();

            $table->enum('status', ['active', 'terminated', 'expired', 'modified'])->default('active');
            $table->date('termination_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'commencement_date']);
        });

        // Amortization schedule lines (one row per payment period)
        Schema::create('lease_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lease_contract_id');
            $table->foreign('lease_contract_id')->references('id')->on('lease_contracts')->cascadeOnDelete();

            $table->unsignedSmallInteger('period_number');
            $table->date('payment_date');

            $table->decimal('opening_balance', 18, 4);
            $table->decimal('payment_amount', 18, 4);
            $table->decimal('interest_portion', 18, 4);
            $table->decimal('principal_portion', 18, 4);
            $table->decimal('closing_balance', 18, 4);

            // Depreciation of ROU asset for finance leases
            $table->decimal('rou_depreciation', 18, 4)->default(0);

            $table->boolean('is_posted')->default(false);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();

            $table->timestamps();

            $table->unique(['lease_contract_id', 'period_number']);
            $table->index(['lease_contract_id', 'is_posted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_schedules');
        Schema::dropIfExists('lease_contracts');
    }
};
