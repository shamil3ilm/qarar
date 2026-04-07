<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FX Derivatives & Hedge Accounting — SAP TRM / IFRS 9 equivalent.
 *
 * Tracks FX forward contracts used to hedge foreign-currency exposures.
 * Supports:
 *   - Fair value hedges (fair value changes through P&L)
 *   - Cash flow hedges (effective portion through OCI)
 *
 * Tables:
 *  - fx_forwards          : FX forward contract master
 *  - fx_hedge_relations   : designation of hedge relationship (hedged item ↔ hedging instrument)
 *  - fx_valuations        : period-end mark-to-market valuations
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_forwards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');

            $table->string('contract_number', 50)->unique();
            $table->string('counterparty_bank')->nullable();
            $table->string('buy_currency', 3);
            $table->string('sell_currency', 3);
            $table->decimal('notional_amount', 18, 4);      // amount in buy_currency
            $table->decimal('forward_rate', 18, 8);         // agreed rate
            $table->date('trade_date');
            $table->date('maturity_date');
            $table->enum('purpose', ['speculative', 'hedge'])->default('hedge');
            $table->enum('status', ['active', 'matured', 'cancelled', 'exercised'])->default('active');

            // Settlement
            $table->decimal('settlement_rate', 18, 8)->nullable();
            $table->decimal('settlement_gain_loss', 18, 4)->nullable();
            $table->date('settled_at')->nullable();

            // GL accounts
            $table->unsignedBigInteger('derivative_asset_account_id')->nullable();
            $table->unsignedBigInteger('unrealised_gain_loss_account_id')->nullable();
            $table->unsignedBigInteger('realised_gain_loss_account_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'maturity_date']);
        });

        Schema::create('fx_hedge_relations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('fx_forward_id');

            $table->string('hedge_type', 20);                // fair_value | cash_flow | net_investment
            $table->string('hedged_item_type', 50);          // sales_order | purchase_order | forecast
            $table->unsignedBigInteger('hedged_item_id')->nullable();
            $table->string('hedged_item_description')->nullable();
            $table->decimal('hedge_ratio', 5, 4)->default(1.0000);  // 0-1

            $table->date('designation_date');
            $table->date('dedesignation_date')->nullable();
            $table->enum('status', ['designated', 'dedesignated', 'expired'])->default('designated');

            $table->text('effectiveness_notes')->nullable();
            $table->timestamps();

            $table->foreign('fx_forward_id')->references('id')->on('fx_forwards')->cascadeOnDelete();
            $table->index(['organization_id', 'fx_forward_id']);
        });

        Schema::create('fx_valuations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fx_forward_id');
            $table->date('valuation_date');
            $table->decimal('spot_rate', 18, 8);
            $table->decimal('fair_value', 18, 4);           // mark-to-market
            $table->decimal('fair_value_change', 18, 4);    // vs previous period
            $table->decimal('effective_portion', 18, 4)->default(0);   // cash flow hedge
            $table->decimal('ineffective_portion', 18, 4)->default(0);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->unique(['fx_forward_id', 'valuation_date']);
            $table->foreign('fx_forward_id')->references('id')->on('fx_forwards')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_valuations');
        Schema::dropIfExists('fx_hedge_relations');
        Schema::dropIfExists('fx_forwards');
    }
};
