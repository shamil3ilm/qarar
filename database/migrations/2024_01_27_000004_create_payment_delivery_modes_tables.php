<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payment methods/modes (configurable per organization)
        Schema::create('payment_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('type', 30); // cash, bank_transfer, card, cheque, upi, mobile_wallet, online, crypto, credit_term, cod
            $table->text('description')->nullable();
            $table->string('icon')->nullable();

            // Bank account link (for auto-reconciliation)
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            // Settings
            $table->boolean('is_online')->default(false); // Online payment method
            $table->boolean('requires_reference')->default(false); // Transaction ref required
            $table->boolean('requires_approval')->default(false);
            $table->decimal('surcharge_percent', 5, 2)->default(0); // Card processing fee
            $table->decimal('surcharge_flat', 15, 2)->default(0);
            $table->decimal('min_amount', 15, 2)->nullable();
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->json('supported_currencies')->nullable();

            // Integration
            $table->string('gateway_provider')->nullable(); // stripe, paypal, razorpay, tap
            $table->json('gateway_config')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'type']);
        });

        // Delivery / shipping methods
        Schema::create('delivery_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('type', 30); // pickup, standard, express, same_day, next_day, freight, digital, custom
            $table->text('description')->nullable();
            $table->string('icon')->nullable();

            // Pricing
            $table->string('pricing_type', 20)->default('flat'); // free, flat, weight_based, value_based, distance_based, custom
            $table->decimal('flat_rate', 15, 2)->default(0);
            $table->json('pricing_rules')->nullable(); // Complex pricing tiers

            // Delivery time
            $table->unsignedSmallInteger('min_delivery_days')->nullable();
            $table->unsignedSmallInteger('max_delivery_days')->nullable();
            $table->string('delivery_time_label')->nullable(); // "2-3 business days"

            // Free shipping threshold
            $table->decimal('free_shipping_min', 15, 2)->nullable();

            // Restrictions
            $table->decimal('max_weight_kg', 10, 2)->nullable();
            $table->decimal('max_value', 15, 2)->nullable();
            $table->json('supported_zones')->nullable(); // Delivery zone IDs
            $table->json('excluded_products')->nullable(); // Product IDs not eligible

            // Tracking
            $table->boolean('tracking_enabled')->default(false);
            $table->string('carrier_provider')->nullable(); // aramex, dhl, fedex, bluedart
            $table->json('carrier_config')->nullable();

            // Availability
            $table->json('available_days')->nullable(); // [1,2,3,4,5] Mon-Fri
            $table->string('cutoff_time', 5)->nullable(); // "14:00" for same-day
            $table->boolean('requires_address')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'type']);
        });

        // Delivery zones (for zone-based shipping)
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->json('countries')->nullable(); // Country codes
            $table->json('states')->nullable(); // State codes
            $table->json('cities')->nullable();
            $table->json('postal_codes')->nullable(); // Ranges or specific codes
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        // Delivery zone rates
        Schema::create('delivery_zone_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_mode_id')->constrained('delivery_modes')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('delivery_zones')->cascadeOnDelete();
            $table->decimal('rate', 15, 2);
            $table->decimal('additional_item_rate', 15, 2)->default(0);
            $table->decimal('min_weight', 10, 2)->default(0);
            $table->decimal('max_weight', 10, 2)->nullable();
            $table->string('currency_code', 3);
            $table->timestamps();

            $table->unique(['delivery_mode_id', 'zone_id']);
        });

        // Shipments (delivery tracking)
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_mode_id')->constrained('delivery_modes')->cascadeOnDelete();
            $table->string('shipment_number', 30);

            // Source document
            $table->string('source_type', 100); // SalesOrder, Invoice, ExchangeOrder
            $table->unsignedBigInteger('source_id')->nullable();

            // Customer
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->json('shipping_address');
            $table->json('billing_address')->nullable();

            // Tracking
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->string('tracking_url')->nullable();

            // Dates
            $table->date('ship_date')->nullable();
            $table->date('estimated_delivery')->nullable();
            $table->date('actual_delivery')->nullable();

            // Weight & dimensions
            $table->decimal('total_weight_kg', 10, 2)->nullable();
            $table->json('dimensions')->nullable(); // {length, width, height}

            // Cost
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->string('currency_code', 3);

            // Status
            $table->string('status', 30)->default('pending'); // pending, picked, packed, shipped, in_transit, out_for_delivery, delivered, failed, returned
            $table->text('notes')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->string('proof_of_delivery_path')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'shipment_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index(['contact_id']);
            $table->index(['tracking_number']);
        });

        // Shipment items
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->string('serial_numbers')->nullable();
            $table->timestamps();

            $table->index(['shipment_id']);
        });

        // Shipment tracking events
        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('status', 50);
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('event_at');
            $table->json('raw_data')->nullable(); // Carrier API response
            $table->timestamps();

            $table->index(['shipment_id', 'event_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_tracking_events');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('delivery_zone_rates');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('delivery_modes');
        Schema::dropIfExists('payment_modes');
    }
};
