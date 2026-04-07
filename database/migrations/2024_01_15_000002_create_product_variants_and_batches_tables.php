<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Units of Measure created in 2024_01_04_000001_create_inventory_tables.php
        // Product variants created in 2024_01_04_000002_create_products_table.php

        // Batch/Lot tracking for inventory
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 50);
            $table->string('lot_number', 50)->nullable();
            $table->string('serial_number', 100)->nullable(); // For serialized items
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('received_date');
            $table->decimal('quantity', 15, 4);
            $table->decimal('reserved_quantity', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4);
            $table->string('status', 20)->default('available'); // available, reserved, expired, damaged, quarantine
            $table->foreignId('supplier_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('grn_number', 50)->nullable(); // Goods Receipt Note reference
            $table->json('metadata')->nullable(); // Additional batch attributes
            $table->timestamps();

            $table->unique(['organization_id', 'product_id', 'warehouse_id', 'batch_number'], 'inv_batches_org_prod_wh_batch_unique');
            $table->index(['organization_id', 'expiry_date']);
            $table->index('serial_number');
        });

        // Product barcodes (multiple barcodes per product)
        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('barcode_value', 100);
            $table->string('barcode_type', 20)->default('code128'); // ean13, ean8, upc, code128, qr
            $table->string('usage', 30)->default('product'); // product, unit, pack
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('quantity', 15, 4)->default(1);
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'barcode_value']);
            $table->index(['organization_id', 'is_active']);
            $table->index(['product_id', 'is_primary']);
        });

        // Product packaging/units (for selling in different units)
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->decimal('conversion_factor', 15, 6); // How many base units
            $table->string('barcode', 50)->nullable();
            $table->decimal('selling_price', 15, 4)->nullable();
            $table->decimal('purchase_price', 15, 4)->nullable();
            $table->boolean('is_purchase_unit')->default(false);
            $table->boolean('is_sales_unit')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
        });

        // Add NEW columns to products table (columns already in 2024_01_04_000002 are skipped)
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type', 30)->default('goods')->after('type');
            // goods, service, consumable, digital, bundle
            $table->foreignId('base_unit_id')->nullable()->after('unit_id')
                ->constrained('units_of_measure')->nullOnDelete();
            // track_inventory, reorder_quantity, weight/dimensions already exist from products create migration
            $table->boolean('track_batches')->default(false);
            $table->boolean('track_serials')->default(false);
            $table->boolean('has_expiry')->default(false);
            $table->unsignedSmallInteger('expiry_warning_days')->nullable();
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('sell_below_cost')->default(true);
            $table->decimal('minimum_stock', 15, 4)->nullable();
            $table->decimal('maximum_stock', 15, 4)->nullable();
            $table->decimal('reorder_point', 15, 4)->nullable();
            $table->boolean('is_loose_item')->default(false);
            $table->decimal('tare_weight', 10, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['base_unit_id']);
            $table->dropColumn([
                'product_type',
                'base_unit_id',
                'track_batches',
                'track_serials',
                'has_expiry',
                'expiry_warning_days',
                'allow_negative_stock',
                'sell_below_cost',
                'minimum_stock',
                'maximum_stock',
                'reorder_point',
                'is_loose_item',
                'tare_weight',
            ]);
        });

        Schema::dropIfExists('product_units');
        Schema::dropIfExists('product_barcodes');
        Schema::dropIfExists('inventory_batches');
        // product_variants dropped by 2024_01_04_000002_create_products_table.php
        // units_of_measure dropped by 2024_01_04_000001_create_inventory_tables.php
    }
};
