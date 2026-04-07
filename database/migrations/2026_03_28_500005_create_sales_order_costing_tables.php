<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sales_order_cost_estimate_items');
        Schema::dropIfExists('sales_order_cost_estimates');

        Schema::create('sales_order_cost_estimates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('sales_order_id')
                ->nullable()
                ->constrained('sales_orders')
                ->name('soce_so_fk');
            $table->foreignId('quotation_id')
                ->nullable()
                ->constrained('quotations')
                ->name('soce_quot_fk');
            $table->foreignId('costing_version_id')
                ->nullable()
                ->constrained('costing_versions')
                ->name('soce_cv_fk');
            $table->enum('status', ['draft', 'released', 'obsolete'])->default('draft');
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->decimal('total_revenue', 18, 4)->default(0);
            $table->decimal('gross_margin', 18, 4)->default(0);
            $table->decimal('gross_margin_percent', 8, 4)->default(0);
            $table->foreignId('costed_by')
                ->nullable()
                ->constrained('users')
                ->name('soce_costed_by_fk');
            $table->dateTime('costed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'sales_order_id'], 'soce_org_so_idx');
            $table->index(['organization_id', 'quotation_id'], 'soce_org_quot_idx');
        });

        Schema::create('sales_order_cost_estimate_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('socei_org_fk');
            $table->foreignId('sales_order_cost_estimate_id')
                ->constrained('sales_order_cost_estimates')
                ->name('socei_estimate_fk');
            $table->foreignId('sales_order_line_id')
                ->nullable()
                ->constrained('sales_order_lines')
                ->name('socei_sol_fk');
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->name('socei_product_fk');
            $table->foreignId('cost_element_id')
                ->nullable()
                ->constrained('cost_elements')
                ->name('socei_ce_fk');
            $table->enum('cost_category', ['material', 'labor', 'overhead', 'other']);
            $table->decimal('quantity', 18, 4);
            $table->decimal('cost_per_unit', 18, 4);
            $table->decimal('total_cost', 18, 4);
            $table->decimal('revenue', 18, 4)->default(0);
            $table->timestamps();

            $table->index(['sales_order_cost_estimate_id'], 'socei_estimate_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_cost_estimate_items');
        Schema::dropIfExists('sales_order_cost_estimates');
    }
};
