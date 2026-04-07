<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lease contracts (SAP RE-FX: Rental Agreement / Lease-In / Lease-Out)
        Schema::create('re_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number', 50);
            $table->string('contract_type', 20)->default('lease_out');
            // contract_type: lease_out (we are landlord) | lease_in (we are tenant)
            $table->foreignId('rental_unit_id')->constrained('re_rental_units')->cascadeOnDelete();
            // counterparty: tenant (lease_out) or landlord (lease_in) — flexible reference
            $table->string('counterparty_type', 30)->nullable(); // contact|vendor|other
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->string('counterparty_name', 200)->nullable(); // denormalized for display
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = indefinite
            $table->date('notice_date')->nullable(); // required notice for termination
            $table->unsignedSmallInteger('notice_period_months')->default(1);
            $table->string('status', 20)->default('draft');
            // status: draft|active|notice_given|expired|terminated|cancelled
            $table->string('currency_code', 5)->default('SAR');
            $table->unsignedSmallInteger('payment_day')->default(1); // day of month rent is due
            $table->string('payment_frequency', 20)->default('monthly');
            // payment_frequency: monthly|quarterly|semi_annual|annual
            $table->boolean('auto_renew')->default(false);
            $table->unsignedSmallInteger('auto_renew_months')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'contract_number'], 're_contracts_org_num_uniq');
            $table->index(['organization_id', 'status', 'end_date'], 're_contracts_org_status_end_idx');
            $table->index(['organization_id', 'rental_unit_id'], 're_contracts_org_unit_idx');
        });

        // Rent conditions — financial terms attached to a contract
        // (SAP RE-FX: Condition type: base_rent, service_charge, deposit, parking, etc.)
        Schema::create('re_contract_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->string('condition_type', 30)->default('base_rent');
            // condition_type: base_rent|service_charge|deposit|parking|storage|other
            $table->string('description', 200)->nullable();
            $table->decimal('amount', 18, 4);
            $table->string('basis', 20)->default('flat');
            // basis: flat|per_sqm|pct_of_rent
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            // Escalation rules
            $table->string('escalation_type', 20)->nullable();
            // escalation_type: none|fixed_pct|cpi|stepped
            $table->decimal('escalation_rate', 8, 4)->nullable(); // pct for fixed_pct
            $table->string('escalation_index', 50)->nullable(); // CPI index name
            $table->string('escalation_frequency', 20)->nullable(); // annual|biennial
            $table->date('next_escalation_date')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['contract_id', 'condition_type', 'is_active'], 're_contract_conditions_contract_type_active_idx');
            $table->index(['next_escalation_date', 'is_active'], 're_contract_conditions_escalation_due_idx');
        });

        // Contract options — renewal, termination break, purchase rights
        Schema::create('re_contract_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->string('option_type', 30)->default('renewal');
            // option_type: renewal|break|purchase|expansion|contraction
            $table->date('exercise_window_start')->nullable();
            $table->date('exercise_window_end')->nullable();
            $table->date('exercise_deadline'); // latest date to exercise
            $table->unsignedSmallInteger('new_term_months')->nullable(); // for renewal options
            $table->decimal('new_rent_amount', 18, 4)->nullable(); // fixed rent if exercised
            $table->string('status', 20)->default('pending');
            // status: pending|exercised|expired|waived
            $table->date('exercised_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'status'], 're_contract_options_contract_status_idx');
            $table->index(['exercise_deadline', 'status'], 're_contract_options_deadline_status_idx');
        });

        // Security deposits — track deposit amounts, payments, interest, refunds
        Schema::create('re_security_deposits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->string('deposit_number', 50);
            $table->decimal('required_amount', 18, 4);
            $table->decimal('collected_amount', 18, 4)->default(0);
            $table->string('currency_code', 5)->default('SAR');
            $table->date('collected_date')->nullable();
            $table->decimal('interest_rate_pct', 8, 4)->default(0); // annual interest on deposit
            $table->decimal('accrued_interest', 18, 4)->default(0);
            $table->string('status', 20)->default('pending');
            // status: pending|partial|collected|partially_refunded|refunded|forfeited
            $table->decimal('refunded_amount', 18, 4)->default(0);
            $table->date('refund_date')->nullable();
            $table->text('refund_reason')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'deposit_number'], 're_security_deposits_org_num_uniq');
            $table->index(['organization_id', 'contract_id'], 're_security_deposits_org_contract_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_security_deposits');
        Schema::dropIfExists('re_contract_options');
        Schema::dropIfExists('re_contract_conditions');
        Schema::dropIfExists('re_contracts');
    }
};
