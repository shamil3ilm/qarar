<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Load plans — vehicle/container-level consolidation (SAP TM: Freight Unit / Load Building)
        Schema::create('tm_load_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('plan_number', 50);
            $table->string('status', 20)->default('open');
            // status: open|building|finalized|dispatched|closed|cancelled
            $table->foreignId('carrier_id')->nullable()->constrained('tm_carriers')->nullOnDelete();
            $table->foreignId('carrier_service_id')->nullable()->constrained('tm_carrier_services')->nullOnDelete();
            $table->string('vehicle_type', 50)->nullable(); // truck|van|container_20ft|container_40ft|etc
            $table->string('vehicle_plate', 30)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('driver_contact', 50)->nullable();
            $table->decimal('max_weight', 14, 3)->nullable(); // kg
            $table->decimal('max_volume', 14, 4)->nullable(); // cbm
            $table->decimal('current_weight', 14, 3)->default(0);
            $table->decimal('current_volume', 14, 4)->default(0);
            $table->decimal('utilization_weight_pct', 5, 2)->default(0); // computed
            $table->decimal('utilization_volume_pct', 5, 2)->default(0); // computed
            $table->dateTime('planned_departure')->nullable();
            $table->dateTime('actual_departure')->nullable();
            $table->string('origin_location', 200)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'plan_number'], 'tm_load_plans_org_num_uniq');
            $table->index(['organization_id', 'status', 'planned_departure'], 'tm_load_plans_org_status_departure_idx');
            $table->index(['organization_id', 'carrier_id', 'status'], 'tm_load_plans_org_carrier_status_idx');
        });

        // Transportation orders — planning unit, distinct from shipment execution
        // (SAP TM: Transportation Order / Freight Order)
        Schema::create('tm_transportation_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 50);
            $table->string('type', 20)->default('outbound'); // outbound|inbound|internal
            $table->string('status', 20)->default('draft');
            // status: draft|planned|tendered|carrier_assigned|in_transit|delivered|cancelled
            $table->foreignId('carrier_id')->nullable()->constrained('tm_carriers')->nullOnDelete();
            $table->foreignId('carrier_service_id')->nullable()->constrained('tm_carrier_services')->nullOnDelete();
            $table->foreignId('load_plan_id')->nullable()->constrained('tm_load_plans')->nullOnDelete();
            $table->foreignId('tender_request_id')->nullable()->constrained('tm_freight_tender_requests')->nullOnDelete();
            $table->string('origin_address', 500)->nullable();
            $table->string('origin_country', 5)->nullable();
            $table->string('destination_address', 500)->nullable();
            $table->string('destination_country', 5)->nullable();
            $table->dateTime('planned_departure')->nullable();
            $table->dateTime('planned_arrival')->nullable();
            $table->dateTime('actual_departure')->nullable();
            $table->dateTime('actual_arrival')->nullable();
            $table->decimal('total_weight', 14, 3)->default(0);
            $table->decimal('total_volume', 14, 4)->default(0);
            $table->decimal('freight_cost', 14, 4)->default(0);
            $table->string('currency_code', 5)->default('USD');
            $table->string('tracking_number', 100)->nullable();
            $table->boolean('has_dangerous_goods')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'order_number'], 'tm_transportation_orders_org_num_uniq');
            $table->index(['organization_id', 'status', 'planned_departure'], 'tm_transportation_orders_org_status_departure_idx');
            $table->index(['organization_id', 'carrier_id', 'status'], 'tm_transportation_orders_org_carrier_status_idx');
            $table->index(['organization_id', 'load_plan_id'], 'tm_transportation_orders_org_load_plan_idx');
        });

        // Items within a transportation order (linked to source documents)
        Schema::create('tm_transportation_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transportation_order_id')->constrained('tm_transportation_orders')->cascadeOnDelete();
            $table->string('reference_type', 30)->nullable();
            // reference_type: sales_order|purchase_order|stock_transfer|shipment|other
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 50)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description', 200);
            $table->decimal('quantity', 14, 3)->default(1);
            $table->string('unit_of_measure', 20)->default('pcs');
            $table->decimal('weight', 14, 3)->default(0);
            $table->decimal('volume', 14, 4)->default(0);
            $table->boolean('is_dangerous_goods')->default(false);
            $table->string('un_number', 10)->nullable();
            $table->timestamps();

            $table->index(['transportation_order_id'], 'tm_transportation_order_items_order_idx');
            $table->index(['reference_type', 'reference_id'], 'tm_transportation_order_items_ref_idx');
        });

        // Load plan items — many transportation orders per load plan
        Schema::create('tm_load_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_plan_id')->constrained('tm_load_plans')->cascadeOnDelete();
            $table->foreignId('transportation_order_id')->constrained('tm_transportation_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('loading_sequence')->default(0);
            $table->timestamps();

            $table->unique(['load_plan_id', 'transportation_order_id'], 'tm_load_plan_items_plan_order_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_load_plan_items');
        Schema::dropIfExists('tm_transportation_order_items');
        Schema::dropIfExists('tm_transportation_orders');
        Schema::dropIfExists('tm_load_plans');
    }
};
