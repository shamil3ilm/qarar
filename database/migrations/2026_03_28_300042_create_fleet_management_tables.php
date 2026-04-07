<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('fleet_number', 20);
            $table->string('license_plate', 20);
            $table->string('make', 50);
            $table->string('model', 50);
            $table->smallInteger('year');
            $table->string('vin', 50)->nullable();
            $table->string('vehicle_type', 30)->default('car')
                ->comment('car/van/truck/motorcycle/bus/other');
            $table->string('fuel_type', 20)->default('petrol')
                ->comment('petrol/diesel/electric/hybrid/cng');
            $table->string('color', 30)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->integer('current_mileage_km')->default(0);
            $table->integer('last_service_km')->nullable();
            $table->integer('next_service_km')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->date('registration_expiry')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'fleet_number'], 'vehicle_org_fleet_idx');
            $table->index(['is_active', 'insurance_expiry'], 'vehicle_active_ins_idx');
        });

        Schema::create('vehicle_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('assigned_from');
            $table->dateTime('assigned_to')->nullable();
            $table->string('purpose', 100)->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->index(['vehicle_id', 'is_current'], 'veh_assign_veh_curr_idx');
            $table->index(['driver_id', 'is_current'], 'veh_assign_drv_curr_idx');
        });

        Schema::create('mileage_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('log_date');
            $table->integer('odometer_start');
            $table->integer('odometer_end');
            $table->integer('distance_km');
            $table->string('trip_purpose', 100)->nullable();
            $table->foreignId('driver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('route', 200)->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'log_date'], 'mileage_veh_date_idx');
        });

        Schema::create('fuel_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('log_date');
            $table->integer('odometer_reading');
            $table->decimal('fuel_quantity_liters', 10, 3);
            $table->decimal('fuel_cost', 18, 4);
            $table->string('currency_code', 3);
            $table->string('fuel_type', 20);
            $table->string('station', 100)->nullable();
            $table->foreignId('filled_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'log_date'], 'fuel_veh_date_idx');
        });

        Schema::create('vehicle_maintenance_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('maintenance_type', 30)
                ->comment('scheduled/unscheduled/repair/inspection');
            $table->date('service_date');
            $table->integer('odometer_reading')->nullable();
            $table->text('description');
            $table->decimal('cost', 18, 4)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('service_provider', 100)->nullable();
            $table->date('next_service_date')->nullable();
            $table->integer('next_service_km')->nullable();
            $table->unsignedBigInteger('maintenance_order_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'service_date'], 'veh_maint_veh_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenance_records');
        Schema::dropIfExists('fuel_logs');
        Schema::dropIfExists('mileage_logs');
        Schema::dropIfExists('vehicle_assignments');
        Schema::dropIfExists('vehicles');
    }
};
