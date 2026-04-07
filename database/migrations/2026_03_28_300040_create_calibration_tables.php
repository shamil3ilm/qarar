<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_equipment', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('equipment_code', 30);
            $table->string('name');
            $table->string('manufacturer', 100)->nullable();
            $table->string('model_number', 50)->nullable();
            $table->string('serial_number', 50)->nullable();
            $table->string('category', 50)->nullable()
                ->comment('thermometer/pressure_gauge/scale/caliper/multimeter/other');
            $table->string('location', 100)->nullable();
            $table->foreignId('responsible_person_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'equipment_code'], 'cal_equip_org_code_idx');
            $table->index(['organization_id', 'is_active'], 'cal_equip_org_active_idx');
        });

        Schema::create('calibration_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('calibration_equipment_id')
                ->constrained('calibration_equipment')
                ->cascadeOnDelete();
            $table->string('plan_code', 30);
            $table->integer('calibration_interval_days');
            $table->decimal('tolerance_low', 10, 4)->nullable();
            $table->decimal('tolerance_high', 10, 4)->nullable();
            $table->string('measurement_unit', 20)->nullable();
            $table->text('calibration_procedure')->nullable();
            $table->string('external_lab', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['calibration_equipment_id', 'is_active'], 'cal_plan_equip_active_idx');
            $table->index(['organization_id', 'plan_code'], 'cal_plan_org_code_idx');
        });

        Schema::create('calibration_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('calibration_equipment_id')
                ->constrained('calibration_equipment')
                ->cascadeOnDelete();
            $table->foreignId('calibration_plan_id')
                ->nullable()
                ->constrained('calibration_plans')
                ->nullOnDelete();
            $table->string('order_number', 30);
            $table->date('scheduled_date');
            $table->date('completed_date')->nullable();
            $table->string('status', 20)->default('planned')
                ->comment('planned/in_progress/completed/overdue/cancelled');
            $table->foreignId('calibrated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_lab', 100)->nullable();
            $table->string('result', 20)->nullable()->comment('pass/fail/conditional');
            $table->decimal('actual_measurement', 12, 4)->nullable();
            $table->text('notes')->nullable();
            $table->date('next_calibration_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['calibration_equipment_id', 'status'], 'cal_order_equip_status_idx');
            $table->index(['scheduled_date', 'status'], 'cal_order_date_status_idx');
            $table->index('calibration_plan_id', 'cal_order_plan_idx');
        });

        Schema::create('calibration_certificates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('calibration_order_id')
                ->constrained('calibration_orders')
                ->cascadeOnDelete();
            $table->string('certificate_number', 50);
            $table->date('issued_date');
            $table->date('valid_until');
            $table->string('issued_by', 100)->nullable();
            $table->string('accreditation_body', 100)->nullable();
            $table->json('certificate_data')->nullable();
            $table->timestamps();

            $table->index('calibration_order_id', 'cal_cert_order_idx');
            $table->index(['organization_id', 'certificate_number'], 'cal_cert_org_num_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_certificates');
        Schema::dropIfExists('calibration_orders');
        Schema::dropIfExists('calibration_plans');
        Schema::dropIfExists('calibration_equipment');
    }
};
