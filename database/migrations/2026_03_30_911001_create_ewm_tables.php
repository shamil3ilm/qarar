<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ewm_storage_types — defines storage type characteristics
        Schema::create('ewm_storage_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('code', 20);  // BLK, SHF, HBY, PLT, FRZ
            $table->string('name', 100);
            $table->enum('type', ['bulk', 'shelving', 'high_bay', 'pallet', 'freezer', 'hazmat', 'open_storage']);
            $table->boolean('allow_partial_putaway')->default(true);
            $table->boolean('mixed_storage')->default(false);
            $table->unsignedSmallInteger('max_weight_kg')->nullable();
            $table->enum('putaway_strategy', ['fifo', 'fefo', 'lifo', 'nearest_bin', 'fixed_bin', 'max_fill', 'open_storage'])->default('fifo');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
            $table->index(['organization_id', 'warehouse_id']);
        });

        // ewm_storage_sections — zones within storage types
        Schema::create('ewm_storage_sections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('storage_type_id')->constrained('ewm_storage_types')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->enum('velocity_class', ['A', 'B', 'C', 'D'])->default('B');  // A=fast-moving
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
        });

        // ewm_bins — individual storage positions (A-01-01-01)
        Schema::create('ewm_bins', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('storage_type_id')->constrained('ewm_storage_types')->restrictOnDelete();
            $table->foreignId('storage_section_id')->nullable()->constrained('ewm_storage_sections')->nullOnDelete();
            $table->string('bin_code', 50);       // A-01-01-01 (aisle-row-column-level)
            $table->string('aisle', 10)->nullable();
            $table->string('row_number', 10)->nullable();
            $table->string('column_number', 10)->nullable();
            $table->string('level', 10)->nullable();
            $table->decimal('max_weight_kg', 10, 2)->nullable();
            $table->decimal('max_volume_m3', 10, 4)->nullable();
            $table->decimal('current_weight_kg', 10, 2)->default(0);
            $table->decimal('fill_pct', 5, 2)->default(0);
            $table->enum('status', ['active', 'blocked', 'inactive', 'reserved'])->default('active');
            $table->boolean('mixed_products')->default(false);
            $table->unsignedBigInteger('current_product_id')->nullable();  // for single-product bins
            $table->timestamps();
            $table->unique(['warehouse_id', 'bin_code']);
            $table->index(['organization_id', 'warehouse_id', 'status']);
            $table->index(['warehouse_id', 'storage_type_id', 'fill_pct']);
        });

        // ewm_transfer_orders — warehouse movement tasks
        Schema::create('ewm_transfer_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('to_number', 30)->unique();  // TO-2026-001234
            $table->enum('movement_type', [
                'goods_receipt',
                'goods_issue',
                'internal_move',
                'replenishment',
                'stock_transfer',
                'physical_inventory',
            ]);
            $table->enum('status', ['created', 'assigned', 'in_progress', 'confirmed', 'cancelled'])->default('created');

            // Source
            $table->foreignId('source_bin_id')->nullable()->constrained('ewm_bins')->nullOnDelete();
            $table->string('source_bin_code', 50)->nullable();

            // Destination
            $table->foreignId('dest_bin_id')->nullable()->constrained('ewm_bins')->nullOnDelete();
            $table->string('dest_bin_code', 50)->nullable();

            // Product
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('requested_qty', 15, 4);
            $table->decimal('confirmed_qty', 15, 4)->nullable();
            $table->string('unit_of_measure', 20)->default('EA');
            $table->string('batch_number', 50)->nullable();
            $table->string('serial_number', 50)->nullable();

            // Traceability
            $table->string('reference_type', 50)->nullable();  // sales_order, purchase_order, etc.
            $table->unsignedBigInteger('reference_id')->nullable();

            // Labor
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->decimal('actual_duration_minutes', 8, 2)->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'warehouse_id', 'status']);
            $table->index(['organization_id', 'movement_type', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });

        // ewm_putaway_rules — per warehouse/storage type putaway configuration
        Schema::create('ewm_putaway_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('storage_type_id')->nullable()->constrained('ewm_storage_types')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedSmallInteger('priority')->default(10);  // lower = higher priority
            $table->enum('strategy', ['fifo', 'fefo', 'lifo', 'nearest_bin', 'fixed_bin', 'max_fill']);
            $table->string('fixed_bin_code', 50)->nullable();  // for fixed-bin strategy
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'warehouse_id', 'priority']);
        });

        // ewm_labor_tasks — WM labor queue
        Schema::create('ewm_labor_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('transfer_order_id')->nullable()->constrained('ewm_transfer_orders')->nullOnDelete();
            $table->enum('task_type', ['pick', 'put', 'move', 'count', 'pack', 'load', 'unload']);
            $table->enum('priority', ['urgent', 'high', 'normal', 'low'])->default('normal');
            $table->enum('status', ['queued', 'assigned', 'in_progress', 'completed', 'cancelled'])->default('queued');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('standard_minutes', 8, 2)->nullable();  // expected task duration
            $table->decimal('actual_minutes', 8, 2)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'warehouse_id', 'status', 'priority'], 'ewm_labor_tasks_org_warehouse_status_priority_idx');
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ewm_labor_tasks');
        Schema::dropIfExists('ewm_putaway_rules');
        Schema::dropIfExists('ewm_transfer_orders');
        Schema::dropIfExists('ewm_bins');
        Schema::dropIfExists('ewm_storage_sections');
        Schema::dropIfExists('ewm_storage_types');
    }
};
