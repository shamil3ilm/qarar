<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer consignment stock levels
        Schema::create('consignment_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('on_hand_quantity', 15, 4)->default(0);
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'contact_id', 'product_id', 'variant_id', 'warehouse_id'],
                'cs_org_contact_prod_variant_wh_unique'
            );
            $table->index(['organization_id', 'contact_id'], 'cs_org_contact_idx');
        });

        // Consignment transaction header
        Schema::create('consignment_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 30);
            $table->enum('order_type', ['fillup', 'issue', 'pickup', 'return']);
            $table->foreignId('contact_id')->constrained('contacts');
            $table->enum('status', ['draft', 'confirmed', 'shipped', 'completed', 'cancelled'])->default('draft');
            $table->date('order_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'order_number'], 'co_org_order_number_unique');
            $table->index(['organization_id', 'order_type'], 'co_org_type_idx');
            $table->index(['organization_id', 'contact_id'], 'co_org_contact_idx');
            $table->index(['organization_id', 'status'], 'co_org_status_idx');
        });

        // Consignment order line items
        Schema::create('consignment_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('consignment_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('unit_id')->constrained('units_of_measure');
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 15, 4)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('notes', 200)->nullable();
            $table->timestamps();

            $table->index(['order_id'], 'col_order_idx');
        });

        // Consignment stock movement log
        Schema::create('consignment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_stock_id')->constrained('consignment_stocks')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('consignment_orders')->cascadeOnDelete();
            $table->enum('movement_type', ['in', 'out']);
            $table->decimal('quantity', 15, 4);
            $table->decimal('balance_after', 15, 4);
            $table->timestamp('moved_at');
            $table->timestamps();

            $table->index(['consignment_stock_id'], 'cm_stock_idx');
            $table->index(['order_id'], 'cm_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_movements');
        Schema::dropIfExists('consignment_order_lines');
        Schema::dropIfExists('consignment_orders');
        Schema::dropIfExists('consignment_stocks');
    }
};
