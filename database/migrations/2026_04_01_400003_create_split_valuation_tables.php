<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Split Valuation — SAP MM split valuation.
 *
 * Allows a single material to carry multiple parallel stock valuations
 * differentiated by a valuation type (e.g. Origin, Quality, Procurement type).
 *
 * Tables:
 *  - inventory_valuation_categories   : per-material valuation category (e.g. "Origin")
 *  - inventory_valuation_types        : concrete types under a category (e.g. "Domestic","Import")
 *  - inventory_split_valuations       : per-(product × warehouse × valuation_type) stock + price
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_valuation_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('product_id');
            $table->string('category_code', 50);   // e.g. "ORIG" – Origin-based split
            $table->string('category_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'product_id', 'category_code'], 'inv_val_cat_org_prod_code_unique');
            $table->index(['organization_id', 'product_id']);
        });

        Schema::create('inventory_valuation_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('valuation_category_id');
            $table->string('type_code', 50);        // e.g. "DOM", "IMP"
            $table->string('type_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['valuation_category_id', 'type_code']);
            $table->index(['organization_id', 'valuation_category_id'], 'inv_val_types_org_cat_idx');
        });

        Schema::create('inventory_split_valuations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('valuation_type_id');

            // Stock quantities
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->decimal('quantity_reserved', 15, 4)->default(0);

            // Valuation
            $table->string('valuation_method', 30)->default('moving_average'); // moving_average | standard
            $table->decimal('moving_average_price', 15, 6)->default(0);
            $table->decimal('standard_price', 15, 6)->default(0);
            $table->decimal('total_stock_value', 15, 2)->default(0);
            $table->string('currency', 3)->default('SAR');

            $table->timestamps();

            $table->unique(['organization_id', 'product_id', 'warehouse_id', 'valuation_type_id'], 'inv_split_val_org_prod_wh_type_unique');
            $table->index(['organization_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_split_valuations');
        Schema::dropIfExists('inventory_valuation_types');
        Schema::dropIfExists('inventory_valuation_categories');
    }
};
