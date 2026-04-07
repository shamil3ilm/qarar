<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('third_party_order_lines');
        Schema::dropIfExists('third_party_orders');

        Schema::create('third_party_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete()->name('tpo_so_fk');
            $table->foreignId('vendor_id')->constrained('contacts')->cascadeOnDelete()->name('tpo_vendor_fk');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete()->name('tpo_po_fk');
            $table->enum('status', ['pending', 'po_created', 'shipped', 'delivered', 'invoiced', 'cancelled'])->default('pending');
            $table->string('shipping_address_line1', 255)->nullable();
            $table->string('shipping_address_line2', 255)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->char('shipping_country_code', 2)->nullable();
            $table->string('vendor_reference', 100)->nullable();
            $table->string('shipping_confirmation', 100)->nullable();
            $table->date('estimated_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status'], 'tpo_org_status_idx');
            $table->index(['sales_order_id'], 'tpo_so_idx');
        });

        Schema::create('third_party_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->name('tpol_org_fk');
            $table->foreignId('third_party_order_id')->constrained('third_party_orders')->cascadeOnDelete()->name('tpol_tpo_fk');
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete()->name('tpol_sol_fk');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->name('tpol_product_fk');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('vendor_price', 18, 4)->nullable();
            $table->timestamps();
            $table->index(['third_party_order_id'], 'tpol_tpo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('third_party_order_lines');
        Schema::dropIfExists('third_party_orders');
    }
};
