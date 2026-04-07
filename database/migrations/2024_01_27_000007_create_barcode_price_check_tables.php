<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Product barcodes (multiple barcodes per product/variant)
        if (!Schema::hasTable('product_barcodes')) {
            Schema::create('product_barcodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();

                // Barcode data
                $table->string('barcode_value', 100); // The actual barcode number
                $table->string('barcode_type', 30); // ean13, ean8, upc_a, upc_e, code128, code39, qr, datamatrix, itf14, isbn, issn, gs1_128, custom
                $table->string('barcode_image_path')->nullable(); // Generated barcode image

                // Purpose
                $table->string('usage', 30)->default('product'); // product, packaging, pallet, internal, shelf, price_tag
                $table->boolean('is_primary')->default(false); // Main barcode for this product

                // GS1 specific fields (for supply chain)
                $table->string('gtin', 14)->nullable(); // Global Trade Item Number
                $table->string('gs1_company_prefix', 12)->nullable();

                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['organization_id', 'barcode_value']);
                $table->index(['barcode_value']); // Fast lookup by scan
                $table->index(['product_id', 'is_primary']);
                $table->index(['barcode_type']);
                $table->index(['gtin']);
            });
        } else {
            // The table was already created by an earlier migration (2024_01_15).
            // That migration creates columns: barcode, barcode_type, is_primary, quantity, unit_id, product_variant_id.
            // Add the extra columns needed for barcode/price-check functionality.
            Schema::table('product_barcodes', function (Blueprint $table) {
                if (!Schema::hasColumn('product_barcodes', 'barcode_value')) {
                    $table->string('barcode_value', 100)->nullable();
                }
                if (!Schema::hasColumn('product_barcodes', 'barcode_image_path')) {
                    $table->string('barcode_image_path')->nullable();
                }
                if (!Schema::hasColumn('product_barcodes', 'usage')) {
                    $table->string('usage', 30)->default('product');
                }
                if (!Schema::hasColumn('product_barcodes', 'gtin')) {
                    $table->string('gtin', 14)->nullable();
                }
                if (!Schema::hasColumn('product_barcodes', 'gs1_company_prefix')) {
                    $table->string('gs1_company_prefix', 12)->nullable();
                }
                if (!Schema::hasColumn('product_barcodes', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                // Earlier migration uses product_variant_id; model uses variant_id, so add it if missing
                if (!Schema::hasColumn('product_barcodes', 'variant_id')) {
                    $table->unsignedBigInteger('variant_id')->nullable();
                }
                // Add batch_id without foreign key constraint (table may be inventory_batches, not product_batches)
                if (!Schema::hasColumn('product_barcodes', 'batch_id')) {
                    $table->unsignedBigInteger('batch_id')->nullable();
                }
            });
        }

        // QR code configurations for products/invoices
        Schema::create('qr_code_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 50); // product, invoice, receipt, price_tag, shelf_label
            $table->string('name');

            // QR content format
            $table->string('content_type', 30); // url, json, vcard, text, custom
            $table->text('content_template'); // Template with {{placeholders}}
            $table->json('included_fields')->nullable(); // Which fields to include

            // Appearance
            $table->unsignedSmallInteger('size_px')->default(200);
            $table->string('foreground_color', 7)->default('#000000');
            $table->string('background_color', 7)->default('#FFFFFF');
            $table->string('logo_path')->nullable(); // Center logo
            $table->string('error_correction', 1)->default('M'); // L, M, Q, H

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'entity_type']);
        });

        // Price check stations / kiosks
        Schema::create('price_check_stations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('station_code', 30);
            $table->string('location_description')->nullable(); // "Aisle 3", "Near entrance"

            // Hardware
            $table->string('device_type', 30)->default('kiosk'); // kiosk, handheld, mobile, tablet, pos
            $table->string('device_id')->nullable(); // Hardware identifier
            $table->string('scanner_type', 30)->default('laser'); // laser, camera, rfid, nfc

            // Supported scan types
            $table->boolean('scan_barcode')->default(true);
            $table->boolean('scan_qr')->default(true);
            $table->boolean('scan_rfid')->default(false);
            $table->boolean('scan_nfc')->default(false);
            $table->boolean('manual_entry')->default(true); // Type SKU/barcode manually

            // Display options
            $table->boolean('show_price')->default(true);
            $table->boolean('show_stock')->default(false);
            $table->boolean('show_promotions')->default(true);
            $table->boolean('show_alternatives')->default(false);
            $table->boolean('show_loyalty_points')->default(false);
            $table->boolean('show_product_image')->default(true);
            $table->boolean('show_description')->default(true);
            $table->boolean('show_location')->default(false); // Aisle/shelf location

            // Price list to use
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
            $table->boolean('use_customer_price')->default(false); // Scan loyalty card first

            // API key for this station
            $table->string('api_token', 64)->unique();

            $table->string('status', 20)->default('active'); // active, inactive, maintenance
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'station_code']);
            $table->index(['branch_id', 'status']);
            $table->index(['api_token']);
        });

        // Price check scan log (analytics)
        Schema::create('price_check_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('price_check_stations')->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            // Scan details
            $table->string('scan_type', 20); // barcode, qr, rfid, nfc, manual, sku
            $table->string('scan_value', 255); // What was scanned
            $table->boolean('scan_successful')->default(true);

            // Product found
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->string('product_sku')->nullable();

            // Price shown
            $table->decimal('displayed_price', 15, 4)->nullable();
            $table->decimal('original_price', 15, 4)->nullable(); // Before promotion
            $table->string('currency_code', 3)->nullable();
            $table->boolean('has_promotion')->default(false);
            $table->string('promotion_name')->nullable();
            $table->decimal('promotion_discount', 15, 2)->nullable();

            // Customer context
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('loyalty_tier')->nullable();

            // Stock info
            $table->decimal('stock_available', 15, 4)->nullable();
            $table->string('stock_status', 20)->nullable(); // in_stock, low_stock, out_of_stock

            // Error handling
            $table->string('error_type', 30)->nullable(); // not_found, inactive, no_price, scan_error
            $table->text('error_message')->nullable();

            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['organization_id', 'scanned_at']);
            $table->index(['branch_id', 'scanned_at']);
            $table->index(['product_id', 'scanned_at']);
            $table->index(['scan_value']);
            $table->index(['station_id', 'scanned_at']);
            $table->index(['error_type']); // Track scan failures
        });

        // Shelf labels / price tags (digital or print)
        Schema::create('shelf_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            // Label content
            $table->string('product_name');
            $table->string('sku');
            $table->string('barcode_value')->nullable();
            $table->decimal('price', 15, 4);
            $table->decimal('compare_at_price', 15, 4)->nullable(); // Strikethrough price
            $table->string('currency_code', 3);
            $table->string('unit_label')->nullable(); // "per kg", "each", "per pack"
            $table->decimal('price_per_unit', 15, 4)->nullable(); // Price per base unit (per 100g, per liter)
            $table->string('unit_measure_label')->nullable(); // "per 100g"

            // Location
            $table->string('aisle')->nullable();
            $table->string('shelf')->nullable();
            $table->string('position')->nullable();

            // Label type
            $table->string('label_type', 20)->default('standard'); // standard, promotional, clearance, new_arrival, organic, halal
            $table->string('label_size', 20)->default('standard'); // small, standard, large, shelf_strip

            // Digital label (ESL - Electronic Shelf Label)
            $table->boolean('is_digital')->default(false);
            $table->string('esl_device_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            // Print status
            $table->boolean('needs_reprint')->default(false);
            $table->timestamp('last_printed_at')->nullable();
            $table->unsignedInteger('print_count')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'branch_id']);
            $table->index(['product_id']);
            $table->index(['barcode_value']);
            $table->index(['needs_reprint']);
        });

        // Add barcode columns to products table (skip if already exists)
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'barcode_type')) {
                $table->string('barcode_type', 30)->nullable()->after('barcode');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'barcode_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['barcode_type']);
            });
        }

        Schema::dropIfExists('shelf_labels');
        Schema::dropIfExists('price_check_logs');
        Schema::dropIfExists('price_check_stations');
        Schema::dropIfExists('qr_code_configs');
        Schema::dropIfExists('product_barcodes');
    }
};
