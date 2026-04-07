<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // HCM — Compensation Management: Pay Grades
        Schema::create('pay_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('grade_code', 20);
            $table->string('grade_name', 100);
            $table->decimal('min_salary', 15, 4);
            $table->decimal('mid_salary', 15, 4);
            $table->decimal('max_salary', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->timestamps();
            $table->unique(['organization_id', 'grade_code'], 'pg_org_code_unique');
        });

        // HCM — Compensation Management: Reviews
        Schema::create('compensation_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('review_name', 100);
            $table->date('review_date');
            $table->date('effective_date');
            $table->decimal('budget_amount', 15, 4)->default(0);
            $table->decimal('allocated_amount', 15, 4)->default(0);
            $table->enum('status', ['draft', 'in_progress', 'approved', 'applied'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status'], 'cr_org_status_idx');
        });

        // HCM — Compensation Management: Review Items
        Schema::create('compensation_review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('compensation_reviews')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('current_salary', 15, 4);
            $table->decimal('proposed_salary', 15, 4)->nullable();
            $table->decimal('increase_amount', 15, 4)->nullable();
            $table->decimal('increase_percentage', 5, 2)->nullable();
            $table->enum('adjustment_type', ['merit', 'promotion', 'market_adjustment', 'equity'])->default('merit');
            $table->text('justification')->nullable();
            $table->enum('status', ['pending', 'recommended', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->index(['review_id', 'employee_id'], 'cri_review_emp_idx');
        });

        // HCM — Position Management
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('position_code', 20);
            $table->string('position_title', 150);
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('pay_grade_id')->nullable()->constrained('pay_grades')->nullOnDelete();
            $table->unsignedBigInteger('reports_to_position_id')->nullable(); // self-referential
            $table->integer('headcount_authorized')->default(1);
            $table->integer('headcount_filled')->default(0);
            $table->boolean('is_key_position')->default(false);
            $table->enum('status', ['active', 'frozen', 'abolished'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'position_code'], 'pos_org_code_unique');
            $table->index(['organization_id', 'department_id'], 'pos_org_dept_idx');
        });

        // HCM — Overtime Policies
        Schema::create('overtime_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('policy_name', 100);
            $table->decimal('daily_standard_hours', 5, 2)->default(8);
            $table->decimal('weekly_standard_hours', 6, 2)->default(40);
            $table->decimal('ot_rate_weekday', 5, 2)->default(1.5);
            $table->decimal('ot_rate_weekend', 5, 2)->default(2.0);
            $table->decimal('ot_rate_holiday', 5, 2)->default(2.5);
            $table->decimal('max_daily_ot_hours', 5, 2)->default(4);
            $table->decimal('max_weekly_ot_hours', 6, 2)->default(12);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'is_active'], 'op_org_active_idx');
        });

        // HCM — Overtime Requests
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('policy_id')->constrained('overtime_policies')->cascadeOnDelete();
            $table->date('ot_date');
            $table->time('ot_start');
            $table->time('ot_end');
            $table->decimal('ot_hours', 5, 2);
            $table->string('reason', 500)->nullable();
            $table->enum('day_type', ['weekday', 'weekend', 'holiday'])->default('weekday');
            $table->decimal('ot_rate', 5, 2);
            $table->decimal('ot_amount', 15, 4)->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['employee_id', 'status'], 'or_emp_status_idx');
            $table->index(['employee_id', 'ot_date'], 'or_emp_date_idx');
        });

        // WM — Warehouse Transfer Orders
        Schema::create('warehouse_transfer_orders', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('to_number', 30);
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->enum('movement_type', ['goods_receipt', 'goods_issue', 'internal_transfer', 'replenishment'])->default('internal_transfer');
            $table->string('source_document_type', 50)->nullable();
            $table->string('source_document_ref', 50)->nullable();
            $table->foreignId('source_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('dest_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->enum('status', ['created', 'in_progress', 'confirmed', 'cancelled'])->default('created');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'to_number'], 'wto_org_number_unique');
            $table->index(['warehouse_id', 'status'], 'wto_wh_status_idx');
        });

        // WM — Warehouse Transfer Order Items
        Schema::create('warehouse_transfer_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_order_id')->constrained('warehouse_transfer_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('source_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('dest_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->decimal('requested_quantity', 15, 4);
            $table->decimal('transferred_quantity', 15, 4)->default(0);
            $table->enum('status', ['open', 'partially_transferred', 'transferred', 'cancelled'])->default('open');
            $table->timestamps();
            $table->index(['transfer_order_id'], 'wtoi_to_idx');
        });

        // PM — Condition-Based Maintenance: Rules
        Schema::create('maintenance_condition_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('rule_name', 100);
            $table->unsignedBigInteger('equipment_id');
            $table->string('measurement_point', 100);
            $table->enum('condition_operator', ['greater_than', 'less_than', 'equals', 'between'])->default('greater_than');
            $table->decimal('threshold_value', 15, 4);
            $table->decimal('threshold_value_to', 15, 4)->nullable();
            $table->string('unit_of_measure', 20)->nullable();
            $table->enum('trigger_action', ['create_order', 'notify', 'both'])->default('both');
            $table->enum('maintenance_type', ['inspection', 'repair', 'overhaul', 'replacement'])->default('inspection');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'equipment_id'], 'mcr_org_equip_idx');
        });

        // PM — Condition-Based Maintenance: Measurements
        Schema::create('maintenance_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('equipment_id');
            $table->string('measurement_point', 100);
            $table->decimal('measurement_value', 15, 4);
            $table->string('unit_of_measure', 20)->nullable();
            $table->timestamp('measured_at');
            $table->foreignId('recorded_by')->constrained('users');
            $table->boolean('threshold_breached')->default(false);
            $table->foreignId('triggered_rule_id')->nullable()->constrained('maintenance_condition_rules')->nullOnDelete();
            $table->timestamps();
            $table->index(['equipment_id', 'measurement_point', 'measured_at'], 'mm_equip_point_date_idx');
        });

        // PM — Spare Parts Planning Integration
        Schema::create('equipment_spare_parts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('recommended_stock_qty', 15, 4)->default(0);
            $table->decimal('current_stock_qty', 15, 4)->default(0);
            $table->boolean('is_critical')->default(false);
            $table->decimal('lead_time_days', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['equipment_id', 'product_id'], 'esp_equip_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_spare_parts');
        Schema::dropIfExists('maintenance_measurements');
        Schema::dropIfExists('maintenance_condition_rules');
        Schema::dropIfExists('warehouse_transfer_order_items');
        Schema::dropIfExists('warehouse_transfer_orders');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('overtime_policies');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('compensation_review_items');
        Schema::dropIfExists('compensation_reviews');
        Schema::dropIfExists('pay_grades');
    }
};
