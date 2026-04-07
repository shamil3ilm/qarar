<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PP — Routing master data: work centers already exist, create routing tables

        Schema::create('routing_headers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('routing_number', 30);
            $table->string('alternative', 5)->default('1');
            $table->boolean('is_default')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'product_id', 'routing_number', 'alternative'], 'rh_org_prod_num_alt_unique');
        });

        Schema::create('routing_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_id')->constrained('routing_headers')->cascadeOnDelete();
            $table->integer('sequence_number'); // 10, 20, 30...
            $table->string('operation_code', 20);
            $table->string('description', 255);
            $table->foreignId('work_center_id')->constrained('work_centers')->cascadeOnDelete();
            $table->decimal('setup_time', 10, 4)->default(0); // hours
            $table->decimal('machine_time', 10, 4)->default(0); // hours per unit
            $table->decimal('labor_time', 10, 4)->default(0); // hours per unit
            $table->string('control_key', 10)->nullable(); // PP01=internal, PP02=external
            $table->timestamps();
            $table->index(['routing_id', 'sequence_number'], 'ro_routing_seq_idx');
        });

        // QM — Inspection lot configuration per product/trigger
        Schema::create('inspection_lot_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('inspection_trigger', ['goods_receipt', 'goods_issue', 'production_completion', 'manual'])->default('goods_receipt');
            $table->boolean('auto_create')->default(true);
            $table->decimal('sample_percentage', 5, 2)->default(100);
            $table->foreignId('quality_plan_id')->nullable()->constrained('quality_plans')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'product_id', 'inspection_trigger'], 'ilc_org_prod_trigger_unique');
        });

        // PS — WBS Hierarchy (extends existing wbs_elements if needed — creating new table)
        // Note: wbs_elements table already created in project systems migration.
        // We only create tables that don't yet exist.

        // PS — Network Activities (task dependencies)
        Schema::create('network_activities', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('wbs_element_id')->nullable()->constrained('wbs_elements')->nullOnDelete();
            $table->string('activity_number', 10); // 0010, 0020
            $table->string('description', 255);
            $table->enum('activity_type', ['internal', 'external', 'general_cost'])->default('internal');
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->decimal('planned_work', 10, 2)->default(0); // hours
            $table->decimal('actual_work', 10, 2)->default(0);
            $table->date('earliest_start')->nullable();
            $table->date('latest_start')->nullable();
            $table->date('earliest_finish')->nullable();
            $table->date('latest_finish')->nullable();
            $table->decimal('float_days', 10, 2)->default(0); // scheduling float
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'cancelled'])->default('not_started');
            $table->timestamps();
            $table->index(['project_id', 'status'], 'na_project_status_idx');
        });

        Schema::create('network_activity_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('predecessor_activity_id')->constrained('network_activities')->cascadeOnDelete();
            $table->foreignId('successor_activity_id')->constrained('network_activities')->cascadeOnDelete();
            $table->enum('relationship_type', ['finish_to_start', 'start_to_start', 'finish_to_finish', 'start_to_finish'])->default('finish_to_start');
            $table->integer('lag_days')->default(0);
            $table->timestamps();
            $table->unique(['predecessor_activity_id', 'successor_activity_id'], 'nar_pred_succ_unique');
        });

        // PS — Earned Value Management snapshots
        Schema::create('earned_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('budget_at_completion', 15, 4)->default(0); // BAC
            $table->decimal('planned_value', 15, 4)->default(0);        // PV (BCWS)
            $table->decimal('earned_value', 15, 4)->default(0);         // EV (BCWP)
            $table->decimal('actual_cost', 15, 4)->default(0);          // AC (ACWP)
            $table->decimal('schedule_variance', 15, 4)->default(0);    // SV = EV - PV
            $table->decimal('cost_variance', 15, 4)->default(0);        // CV = EV - AC
            $table->decimal('schedule_performance_index', 8, 4)->default(1); // SPI = EV/PV
            $table->decimal('cost_performance_index', 8, 4)->default(1);     // CPI = EV/AC
            $table->decimal('estimate_at_completion', 15, 4)->default(0);    // EAC = BAC/CPI
            $table->decimal('estimate_to_complete', 15, 4)->default(0);      // ETC = EAC - AC
            $table->decimal('variance_at_completion', 15, 4)->default(0);    // VAC = BAC - EAC
            $table->timestamps();
            $table->index(['project_id', 'snapshot_date'], 'evs_project_date_idx');
        });

        // PS — Project Settlement Rules
        Schema::create('project_settlement_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('wbs_element_id')->nullable()->constrained('wbs_elements')->nullOnDelete();
            $table->enum('receiver_type', ['cost_center', 'gl_account', 'internal_order', 'profit_center'])->default('gl_account');
            $table->unsignedBigInteger('receiver_id');
            $table->decimal('settlement_percentage', 5, 2)->default(100);
            $table->timestamps();
            $table->index(['project_id'], 'psr_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_settlement_rules');
        Schema::dropIfExists('earned_value_snapshots');
        Schema::dropIfExists('network_activity_relationships');
        Schema::dropIfExists('network_activities');
        Schema::dropIfExists('inspection_lot_configs');
        Schema::dropIfExists('routing_operations');
        Schema::dropIfExists('routing_headers');
    }
};
