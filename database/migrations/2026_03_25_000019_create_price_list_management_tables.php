<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // price_lists and price_list_items already exist from 2024_01_15_000001_create_pricing_system_tables
        // This migration adds the missing assignment and volume-break tables only.

        // Assign price list to customer / customer group / all
        Schema::create('price_list_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->enum('assignment_type', ['contact', 'customer_group', 'all']);
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->tinyInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['price_list_id'], 'pla_list_idx');
            $table->index(['assignment_type', 'assignment_id'], 'pla_type_id_idx');
        });

        // Tiered / volume break pricing
        Schema::create('price_volume_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('min_qty', 15, 4);
            $table->decimal('max_qty', 15, 4)->nullable();
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->timestamps();

            $table->index(['price_list_id', 'product_id'], 'pvb_list_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_volume_breaks');
        Schema::dropIfExists('price_list_assignments');
    }
};
