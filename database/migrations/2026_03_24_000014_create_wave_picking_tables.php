<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('putaway_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('warehouse_zone')->nullable();
            $table->foreignId('preferred_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->tinyInteger('priority')->default(10)->comment('Lower number = higher priority');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'warehouse_id']);
        });

        Schema::create('wave_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('wave_number');
            $table->enum('wave_type', ['outbound', 'replenishment', 'returns'])->default('outbound');
            $table->enum('status', ['draft', 'released', 'picking', 'completed', 'cancelled'])->default('draft');
            $table->date('planned_date');
            $table->integer('total_orders')->default(0);
            $table->integer('total_lines')->default(0);
            $table->decimal('total_units', 12, 4)->default(0);
            $table->timestamp('released_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('wave_plan_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wave_plan_id')->constrained('wave_plans')->cascadeOnDelete();
            $table->enum('order_type', ['sales_order', 'stock_transfer', 'purchase_return'])->default('sales_order');
            $table->unsignedBigInteger('order_id');
            $table->timestamps();

            $table->unique(['wave_plan_id', 'order_type', 'order_id']);
        });

        Schema::create('picking_lists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('wave_plan_id')->nullable()->constrained('wave_plans')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('list_number');
            $table->foreignId('picker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'partial', 'cancelled'])->default('pending');
            $table->enum('picking_type', ['single_order', 'multi_order', 'zone', 'cluster'])->default('single_order');
            $table->integer('total_lines')->default(0);
            $table->integer('picked_lines')->default(0);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['warehouse_id', 'picker_id']);
        });

        Schema::create('picking_list_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('picking_list_id')->constrained('picking_lists')->cascadeOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->decimal('required_quantity', 12, 4);
            $table->decimal('picked_quantity', 12, 4)->default(0);
            $table->enum('status', ['pending', 'partial', 'completed', 'skipped'])->default('pending');
            $table->timestamp('picked_at')->nullable();
            $table->foreignId('picked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['picking_list_id', 'status']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picking_list_lines');
        Schema::dropIfExists('picking_lists');
        Schema::dropIfExists('wave_plan_orders');
        Schema::dropIfExists('wave_plans');
        Schema::dropIfExists('putaway_rules');
    }
};
