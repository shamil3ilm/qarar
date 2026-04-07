<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('work_order_co_product_actuals');
        Schema::dropIfExists('bom_co_products');

        Schema::create('bom_co_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_template_id')->constrained('bom_templates')->cascadeOnDelete()->name('bcp_bom_fk');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->name('bcp_product_fk');
            $table->enum('co_product_type', ['co_product', 'by_product', 'scrap'])->default('co_product');
            $table->decimal('quantity_per_base', 18, 4);
            $table->string('unit_of_measure', 20)->nullable();
            $table->decimal('cost_allocation_percent', 5, 2)->default(0);
            $table->boolean('is_valuated')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['bom_template_id'], 'bcp_bom_idx');
            $table->index(['product_id'], 'bcp_product_idx');
        });

        Schema::create('work_order_co_product_actuals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->name('wocpa_org_fk');
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete()->name('wocpa_wo_fk');
            $table->foreignId('bom_co_product_id')->nullable()->constrained('bom_co_products')->nullOnDelete()->name('wocpa_bcp_fk');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->name('wocpa_product_fk');
            $table->enum('co_product_type', ['co_product', 'by_product', 'scrap'])->default('co_product');
            $table->decimal('planned_quantity', 18, 4)->default(0);
            $table->decimal('actual_quantity', 18, 4)->default(0);
            $table->string('unit_of_measure', 20)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->name('wocpa_warehouse_fk');
            $table->boolean('posted_to_stock')->default(false);
            $table->timestamps();

            $table->index(['work_order_id'], 'wocpa_wo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_co_product_actuals');
        Schema::dropIfExists('bom_co_products');
    }
};
