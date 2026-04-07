<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop in reverse FK order
        Schema::dropIfExists('scheduling_conflicts');
        Schema::dropIfExists('capacity_reservations');
        Schema::dropIfExists('capacity_slots');
        Schema::dropIfExists('scheduling_runs');

        Schema::create('scheduling_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('run_number')->unique();
            $table->enum('scheduling_type', ['forward', 'backward', 'finite', 'infinite']);
            $table->date('horizon_start');
            $table->date('horizon_end');
            $table->json('work_center_ids')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('run_by')->constrained('users', 'id', 'sched_run_usr_fk');
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('capacity_slots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('work_center_id')->constrained('work_centers', 'id', 'cap_slot_wc_fk');
            $table->date('slot_date');
            $table->time('slot_start');
            $table->time('slot_end');
            $table->unsignedSmallInteger('available_minutes');
            $table->unsignedSmallInteger('allocated_minutes')->default(0);
            $table->decimal('utilization_pct', 5, 2)->storedAs(
                'CASE WHEN available_minutes = 0 THEN 0 ELSE ROUND(allocated_minutes / available_minutes * 100, 2) END'
            );
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('capacity_reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('capacity_slot_id')->constrained('capacity_slots', 'id', 'cap_res_slot_fk');
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders', 'id', 'cap_res_wo_fk');
            $table->foreignId('routing_operation_id')->nullable()->constrained('routing_operations', 'id', 'cap_res_op_fk');
            $table->unsignedSmallInteger('reserved_minutes');
            $table->enum('status', ['tentative', 'confirmed', 'released'])->default('tentative');
            $table->timestamps();
        });

        Schema::create('scheduling_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('scheduling_run_id')->constrained('scheduling_runs', 'id', 'sched_conf_run_fk');
            $table->foreignId('work_center_id')->constrained('work_centers', 'id', 'sched_conf_wc_fk');
            $table->date('conflict_date');
            $table->unsignedInteger('required_minutes');
            $table->unsignedInteger('available_minutes');
            $table->unsignedInteger('overload_minutes');
            $table->json('affected_order_ids');
            $table->enum('resolution_applied', ['none', 'delayed', 'split', 'outsourced'])->default('none');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_conflicts');
        Schema::dropIfExists('capacity_reservations');
        Schema::dropIfExists('capacity_slots');
        Schema::dropIfExists('scheduling_runs');
    }
};
