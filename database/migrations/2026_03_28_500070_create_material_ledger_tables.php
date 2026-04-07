<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ml_price_differences');
        Schema::dropIfExists('ml_closing_entries');
        Schema::dropIfExists('ml_documents');
        Schema::dropIfExists('material_ledger_records');

        Schema::create('material_ledger_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('product_id')->constrained('products')->name('mlr_product_fk');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->name('mlr_warehouse_fk');
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->decimal('opening_stock_qty', 18, 4)->default(0);
            $table->decimal('opening_stock_value', 18, 4)->default(0);
            $table->decimal('closing_stock_qty', 18, 4)->default(0);
            $table->decimal('closing_stock_value', 18, 4)->default(0);
            $table->decimal('cumulative_receipts_qty', 18, 4)->default(0);
            $table->decimal('cumulative_receipts_value', 18, 4)->default(0);
            $table->decimal('cumulative_issues_qty', 18, 4)->default(0);
            $table->decimal('cumulative_issues_value', 18, 4)->default(0);
            $table->decimal('standard_price', 18, 4)->default(0);
            $table->decimal('actual_price', 18, 4)->default(0);
            $table->decimal('price_difference', 18, 4)->default(0);
            $table->unsignedInteger('price_unit')->default(1);
            $table->char('currency_code', 3)->default('SAR');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'product_id', 'warehouse_id', 'period', 'fiscal_year'],
                'mlr_org_prod_wh_per_fy_unq'
            );
            $table->index(['organization_id', 'period', 'fiscal_year'], 'mlr_org_period_fy_idx');
        });

        Schema::create('ml_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('mld_org_fk');
            $table->foreignId('material_ledger_record_id')
                ->constrained('material_ledger_records')
                ->name('mld_mlr_fk');
            $table->enum('document_type', [
                'goods_receipt',
                'goods_issue',
                'invoice',
                'transfer',
                'adjustment',
                'closing',
            ])->default('goods_receipt');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('standard_value', 18, 4);
            $table->decimal('actual_value', 18, 4)->nullable();
            $table->decimal('price_difference', 18, 4)->default(0);
            $table->date('posting_date');
            $table->timestamps();

            $table->index(['material_ledger_record_id'], 'mld_mlr_idx');
        });

        Schema::create('ml_closing_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('mlce_org_fk');
            $table->foreignId('material_ledger_record_id')
                ->constrained('material_ledger_records')
                ->name('mlce_mlr_fk');
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('total_price_difference', 18, 4);
            $table->decimal('revaluation_amount', 18, 4);
            $table->decimal('actual_price_calculated', 18, 4);
            $table->foreignId('run_by')->nullable()->constrained('users')->name('mlce_run_by_fk');
            $table->dateTime('run_at');
            $table->timestamps();

            $table->index(['organization_id', 'period', 'fiscal_year'], 'mlce_org_period_fy_idx');
        });

        Schema::create('ml_price_differences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->name('mlpd_org_fk');
            $table->foreignId('ml_closing_entry_id')
                ->constrained('ml_closing_entries')
                ->name('mlpd_ce_fk');
            $table->foreignId('product_id')->constrained('products')->name('mlpd_product_fk');
            $table->enum('category', [
                'purchase_price_variance',
                'exchange_rate_difference',
                'invoice_difference',
                'production_variance',
            ])->default('purchase_price_variance');
            $table->decimal('amount', 18, 4);
            $table->decimal('quantity_affected', 18, 4);
            $table->timestamps();

            $table->index(['ml_closing_entry_id'], 'mlpd_ce_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_price_differences');
        Schema::dropIfExists('ml_closing_entries');
        Schema::dropIfExists('ml_documents');
        Schema::dropIfExists('material_ledger_records');
    }
};
