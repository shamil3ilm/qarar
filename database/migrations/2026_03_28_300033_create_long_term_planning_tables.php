<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ltp_capacity_requirements');
        Schema::dropIfExists('ltp_planned_orders');
        Schema::dropIfExists('ltp_simulations');

        Schema::create('ltp_simulations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('planning_horizon_from');
            $table->date('planning_horizon_to');
            $table->string('status', 20)->default('draft');
            $table->foreignId('mrp_run_id')
                ->nullable()
                ->constrained('mrp_runs')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['organization_id', 'status'],
                'ltp_sim_org_status_idx'
            );
        });

        Schema::create('ltp_planned_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ltp_simulation_id')
                ->constrained('ltp_simulations')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('planned_order_type', 20);
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->date('planned_start');
            $table->date('planned_finish');
            $table->foreignId('production_version_id')
                ->nullable()
                ->constrained('production_versions')
                ->nullOnDelete();
            $table->foreignId('vendor_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['product_id', 'planned_start'],
                'ltp_po_product_start_idx'
            );
        });

        Schema::create('ltp_capacity_requirements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ltp_simulation_id')
                ->constrained('ltp_simulations')
                ->cascadeOnDelete();
            $table->foreignId('work_center_id')
                ->constrained('work_centers')
                ->cascadeOnDelete();
            $table->date('calendar_date');
            $table->decimal('required_hours', 10, 4);
            $table->decimal('available_hours', 10, 4);
            $table->decimal('utilization_percentage', 6, 2);
            $table->timestamps();

            $table->index(
                ['work_center_id', 'calendar_date'],
                'ltp_cap_wc_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ltp_capacity_requirements');
        Schema::dropIfExists('ltp_planned_orders');
        Schema::dropIfExists('ltp_simulations');
    }
};
