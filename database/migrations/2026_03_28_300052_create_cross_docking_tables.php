<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cross_docking_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('inbound_source_type', 30)
                ->comment('purchase_order/transfer_order/return');
            $table->unsignedBigInteger('inbound_source_id');
            $table->string('outbound_dest_type', 30)
                ->comment('sales_order/transfer_order/delivery');
            $table->unsignedBigInteger('outbound_dest_id');
            $table->dateTime('planned_date');
            $table->dateTime('actual_date')->nullable();
            $table->string('status', 20)->default('planned')
                ->comment('planned/in_progress/completed/cancelled');
            $table->unsignedBigInteger('dock_door_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status'], 'xdock_wh_status_idx');
            $table->index(['status', 'planned_date'], 'xdock_status_date_idx');
            $table->index(['inbound_source_type', 'inbound_source_id'], 'xdock_inbound_idx');
            $table->index(['outbound_dest_type', 'outbound_dest_id'], 'xdock_outbound_idx');
        });

        Schema::create('cross_docking_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cross_docking_order_id')
                ->constrained('cross_docking_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('quantity_transferred', 18, 4)->default(0);
            $table->string('status', 20)->default('pending')
                ->comment('pending/transferred/partial');
            $table->timestamps();

            $table->index('cross_docking_order_id', 'xdock_line_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_docking_order_lines');
        Schema::dropIfExists('cross_docking_orders');
    }
};
