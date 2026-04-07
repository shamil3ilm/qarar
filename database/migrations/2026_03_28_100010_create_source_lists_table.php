<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates two tables that drive vendor sourcing decisions:
 *
 * vendor_product_pricing  — one record per (vendor, product) pair storing the
 *                           negotiated price, lead time, and order quantities.
 *                           Equivalent to SAP Purchasing Info Records (PIR).
 *
 * vendor_source_lists     — ranked list of approved vendors per product,
 *                           referencing a vendor_product_pricing row and
 *                           adding priority, quota, and block flags.
 *                           Equivalent to SAP Source Lists.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop in reverse-dependency order to allow safe re-runs after a
        // previously failed migration left partial tables behind.
        Schema::dropIfExists('vendor_source_lists');
        Schema::dropIfExists('vendor_product_pricing');

        // ------------------------------------------------------------------
        // vendor_product_pricing
        // Stores negotiated price, lead time, and minimum order quantity for
        // a specific (organization, vendor, product) combination.
        // ------------------------------------------------------------------
        Schema::create('vendor_product_pricing', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->index();

            // The product this pricing record applies to
            $table->unsignedBigInteger('product_id');
            // The vendor (contact) offering this price
            $table->unsignedBigInteger('vendor_id');

            // Vendor's own code and description for this product
            $table->string('vendor_product_code', 100)->nullable();
            $table->string('vendor_product_description', 500)->nullable();

            // Pricing
            $table->decimal('unit_price', 15, 4);
            $table->char('currency_code', 3)->default('SAR');

            // Procurement terms
            $table->unsignedInteger('lead_time_days')->default(7);
            $table->decimal('minimum_order_quantity', 10, 4)->default(1);
            $table->decimal('order_quantity_multiple', 10, 4)->nullable()
                ->comment('Quantity must be ordered in multiples of this value');

            // Validity window (null = no restriction)
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // Whether this is the default vendor for the product
            $table->boolean('is_preferred_vendor')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('contacts')->onDelete('cascade');

            // Composite indexes with explicit short names (MySQL max 64 chars)
            $table->index(['organization_id', 'product_id', 'vendor_id'], 'vpp_org_product_vendor_idx');
            $table->index(['organization_id', 'product_id', 'is_preferred_vendor'], 'vpp_org_product_preferred_idx');
        });

        // ------------------------------------------------------------------
        // vendor_source_lists
        // Ordered list of approved vendors per product. Purchasing can lock
        // a product to a fixed vendor, block a vendor, or split volume by
        // quota percentage across multiple vendors.
        // ------------------------------------------------------------------
        Schema::create('vendor_source_lists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->index();

            // The product being sourced
            $table->unsignedBigInteger('product_id');
            // The approved vendor
            $table->unsignedBigInteger('vendor_id');
            // Optional link to the negotiated pricing record for this vendor/product
            $table->unsignedBigInteger('vendor_product_pricing_id')->nullable();

            // Optional plant/location scope (null = all locations)
            $table->string('plant_code', 50)->nullable();

            // Validity window (null = no restriction)
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // If true, purchasing orders MUST use this vendor (no alternatives)
            $table->boolean('is_fixed_vendor')->default(false);
            // If true, this vendor cannot be used for new orders
            $table->boolean('is_blocked')->default(false);

            // Lower number = higher preference (1 = most preferred)
            $table->unsignedInteger('priority')->default(1);
            // Percentage of demand volume to route to this vendor (null = 100%)
            $table->decimal('quota_percentage', 5, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('vendor_product_pricing_id')
                ->references('id')
                ->on('vendor_product_pricing')
                ->onDelete('set null');

            // Composite index with explicit short name
            $table->index(
                ['organization_id', 'product_id', 'is_blocked', 'priority'],
                'vsl_org_product_blocked_priority_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_source_lists');
        Schema::dropIfExists('vendor_product_pricing');
    }
};
