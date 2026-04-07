<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Periodic posting run headers (SAP RE-FX: Periodic Posting)
        // Generates rent invoices / expense accruals for all active contracts
        Schema::create('re_posting_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('run_number', 50);
            $table->string('type', 30)->default('rent');
            // type: rent|service_charge|deposit_interest|all
            $table->date('posting_date');
            $table->integer('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('status', 20)->default('draft');
            // status: draft|simulated|posted|reversed
            $table->unsignedInteger('contracts_processed')->default(0);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->string('currency_code', 5)->default('SAR');
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('executed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'run_number'], 're_posting_runs_org_num_uniq');
            $table->index(['organization_id', 'type', 'period_year', 'period_month'], 're_posting_runs_org_type_period_idx');
        });

        // Individual posting lines within a run
        Schema::create('re_posting_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('posting_run_id')->constrained('re_posting_runs')->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->foreignId('condition_id')->constrained('re_contract_conditions')->cascadeOnDelete();
            $table->string('condition_type', 30);
            $table->decimal('amount', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->string('status', 20)->default('pending'); // pending|posted|skipped|error
            $table->string('error_message', 500)->nullable();
            $table->timestamps();

            $table->index(['posting_run_id', 'status'], 're_posting_run_items_run_status_idx');
            $table->index(['contract_id'], 're_posting_run_items_contract_idx');
        });

        // Service charge settlement (SAP RE-FX: Service Charge Settlement)
        // Reconciles actual operating costs against estimated amounts billed to tenants
        Schema::create('re_service_charge_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('settlement_number', 50);
            $table->foreignId('property_id')->constrained('re_properties')->cascadeOnDelete();
            $table->integer('settlement_year');
            $table->string('status', 20)->default('draft');
            // status: draft|calculated|approved|invoiced|closed
            $table->decimal('total_actual_costs', 18, 4)->default(0);
            $table->decimal('total_billed_to_tenants', 18, 4)->default(0);
            $table->decimal('total_adjustment', 18, 4)->default(0); // positive = tenant owes, negative = refund
            $table->string('currency_code', 5)->default('SAR');
            $table->date('settlement_date')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'settlement_number'], 're_service_charge_settlements_org_num_uniq');
            $table->index(['organization_id', 'property_id', 'settlement_year'], 're_service_charge_settlements_org_property_year_idx');
        });

        // Individual cost items within a service charge settlement
        Schema::create('re_service_charge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('re_service_charge_settlements')->cascadeOnDelete();
            $table->string('cost_category', 100); // electricity|water|cleaning|security|maintenance|insurance|etc
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->decimal('lettable_area_sqm', 14, 4)->default(0); // total apportionable area
            $table->decimal('cost_per_sqm', 14, 6)->default(0); // computed
            $table->string('allocation_basis', 30)->default('area');
            // allocation_basis: area|equal|usage|custom
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['settlement_id'], 're_service_charge_items_settlement_idx');
        });

        // Per-tenant allocation within a service charge settlement
        Schema::create('re_service_charge_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('re_service_charge_settlements')->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->decimal('unit_area_sqm', 14, 4)->default(0);
            $table->decimal('allocation_pct', 8, 4)->default(0);
            $table->decimal('actual_amount', 18, 4)->default(0); // tenant's share of actual costs
            $table->decimal('billed_amount', 18, 4)->default(0); // what tenant already paid on account
            $table->decimal('adjustment_amount', 18, 4)->default(0); // positive = additional charge, negative = refund
            $table->timestamps();

            $table->unique(['settlement_id', 'contract_id'], 're_service_charge_allocations_settlement_contract_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_service_charge_allocations');
        Schema::dropIfExists('re_service_charge_items');
        Schema::dropIfExists('re_service_charge_settlements');
        Schema::dropIfExists('re_posting_run_items');
        Schema::dropIfExists('re_posting_runs');
    }
};
