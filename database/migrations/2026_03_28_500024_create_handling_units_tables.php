<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('handling_unit_items');
        Schema::dropIfExists('handling_units');

        Schema::create('handling_units', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete()->name('hu_shipment_fk');
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete()->name('hu_so_fk');
            $table->enum('hu_type', ['box', 'pallet', 'container', 'bag', 'drum', 'other'])->default('box');
            $table->string('hu_number', 50);
            $table->string('sscc_number', 30)->nullable();
            $table->decimal('gross_weight', 10, 4)->nullable();
            $table->decimal('net_weight', 10, 4)->nullable();
            $table->decimal('volume', 10, 4)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->boolean('is_sealed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'hu_number'], 'hu_org_number_unq');
            $table->index(['shipment_id'], 'hu_shipment_idx');
        });

        Schema::create('handling_unit_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->name('hui_org_fk');
            $table->foreignId('handling_unit_id')->constrained('handling_units')->cascadeOnDelete()->name('hui_hu_fk');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete()->name('hui_product_fk');
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete()->name('hui_batch_fk');
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete()->name('hui_sol_fk');
            $table->decimal('quantity', 18, 4);
            $table->decimal('weight', 10, 4)->nullable();
            $table->timestamps();
            $table->index(['handling_unit_id'], 'hui_hu_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handling_unit_items');
        Schema::dropIfExists('handling_units');
    }
};
