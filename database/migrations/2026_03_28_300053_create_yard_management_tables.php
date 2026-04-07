<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yard_zones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('zone_code', 20);
            $table->string('name', 100);
            $table->string('zone_type', 20)->default('staging')
                ->comment('staging/parking/inspection/dock');
            $table->unsignedInteger('capacity_vehicles')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'is_active'], 'yard_zone_wh_active_idx');
            $table->unique(['warehouse_id', 'zone_code'], 'yard_zone_wh_code_uq');
        });

        Schema::create('dock_doors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('door_code', 10);
            $table->string('door_type', 20)->default('combined')
                ->comment('inbound/outbound/combined');
            $table->foreignId('yard_zone_id')->nullable()->constrained('yard_zones')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('status', 20)->default('available')
                ->comment('available/occupied/maintenance');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status'], 'dock_door_wh_status_idx');
            $table->unique(['warehouse_id', 'door_code'], 'dock_door_wh_code_uq');
        });

        Schema::create('truck_appointments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('appointment_number', 20);
            $table->dateTime('scheduled_arrival');
            $table->dateTime('scheduled_departure')->nullable();
            $table->dateTime('actual_arrival')->nullable();
            $table->dateTime('actual_departure')->nullable();
            $table->foreignId('dock_door_id')->nullable()->constrained('dock_doors')->nullOnDelete();
            $table->foreignId('yard_zone_id')->nullable()->constrained('yard_zones')->nullOnDelete();
            $table->string('vehicle_plate', 20)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->string('appointment_type', 20)->default('delivery')
                ->comment('delivery/pickup/both');
            $table->string('reference_type', 30)->nullable()
                ->comment('purchase_order/sales_order/transfer');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('status', 20)->default('scheduled')
                ->comment('scheduled/checked_in/docked/loading/departed/cancelled');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status'], 'truck_appt_wh_status_idx');
            $table->index(['scheduled_arrival', 'status'], 'truck_appt_sched_status_idx');
            $table->index(['dock_door_id', 'status'], 'truck_appt_dock_status_idx');
            $table->index(['vendor_id', 'scheduled_arrival'], 'truck_appt_vendor_sched_idx');
            $table->unique(['warehouse_id', 'appointment_number'], 'truck_appt_wh_num_uq');
        });

        Schema::create('yard_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('truck_appointment_id')
                ->constrained('truck_appointments')->cascadeOnDelete();
            $table->foreignId('from_zone_id')->nullable()->constrained('yard_zones')->nullOnDelete();
            $table->foreignId('to_zone_id')->nullable()->constrained('yard_zones')->nullOnDelete();
            $table->foreignId('from_dock_id')->nullable()->constrained('dock_doors')->nullOnDelete();
            $table->foreignId('to_dock_id')->nullable()->constrained('dock_doors')->nullOnDelete();
            $table->string('movement_type', 20)
                ->comment('arrival/move_to_dock/move_to_zone/departure');
            $table->dateTime('moved_at');
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('truck_appointment_id', 'yard_mov_appt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yard_movements');
        Schema::dropIfExists('truck_appointments');
        Schema::dropIfExists('dock_doors');
        Schema::dropIfExists('yard_zones');
    }
};
