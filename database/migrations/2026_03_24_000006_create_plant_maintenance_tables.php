<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Functional Locations (plant hierarchy: plant > area > line > machine > component)
        Schema::create('functional_locations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('location_type', ['plant', 'area', 'line', 'machine', 'component'])->default('area');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('functional_locations')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->index('organization_id');
        });

        // Equipment Categories
        Schema::create('equipment_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        // Equipment master records
        Schema::create('equipment', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('functional_location_id')->nullable();
            $table->unsignedBigInteger('equipment_category_id')->nullable();
            $table->string('equipment_number');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_cost', 12, 2)->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->enum('status', ['active', 'under_maintenance', 'decommissioned', 'scrapped'])->default('active');
            $table->date('last_maintenance_date')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'equipment_number']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('functional_location_id')->references('id')->on('functional_locations')->nullOnDelete();
            $table->foreign('equipment_category_id')->references('id')->on('equipment_categories')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'status']);
        });

        // Maintenance Plans (preventive / predictive schedules)
        Schema::create('maintenance_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('equipment_id');
            $table->string('name');
            $table->enum('maintenance_type', ['preventive', 'predictive', 'condition_based'])->default('preventive');
            $table->enum('frequency_type', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'hours', 'kilometers'])->default('monthly');
            $table->unsignedInteger('frequency_value')->default(1);
            $table->decimal('estimated_duration_hours', 5, 2)->default(1.00);
            $table->text('description')->nullable();
            $table->json('tasks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_generated_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('equipment_id')->references('id')->on('equipment')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('organization_id');
            $table->index('equipment_id');
        });

        // Maintenance Orders (work tickets)
        Schema::create('maintenance_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('order_number');
            $table->unsignedBigInteger('maintenance_plan_id')->nullable();
            $table->unsignedBigInteger('equipment_id');
            $table->enum('order_type', ['preventive', 'corrective', 'emergency', 'inspection'])->default('corrective');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'on_hold', 'completed', 'cancelled'])->default('open');
            $table->text('description');
            $table->dateTime('scheduled_start')->nullable();
            $table->dateTime('scheduled_end')->nullable();
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_end')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->decimal('downtime_hours', 6, 2)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'order_number']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('maintenance_plan_id')->references('id')->on('maintenance_plans')->nullOnDelete();
            $table->foreign('equipment_id')->references('id')->on('equipment')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'status', 'priority']);
            $table->index(['equipment_id', 'status']);
        });

        // Tasks within a maintenance order
        Schema::create('maintenance_order_tasks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('maintenance_order_id');
            $table->string('task_description');
            $table->boolean('is_safety_critical')->default(false);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('maintenance_order_id')->references('id')->on('maintenance_orders')->cascadeOnDelete();
            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
            $table->index('maintenance_order_id');
        });

        // Parts/materials consumed by a maintenance order
        Schema::create('maintenance_order_parts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('maintenance_order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description');
            $table->decimal('quantity_required', 10, 4)->default(1.0000);
            $table->decimal('quantity_used', 10, 4)->default(0.0000);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->timestamps();

            $table->foreign('maintenance_order_id')->references('id')->on('maintenance_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index('maintenance_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_order_parts');
        Schema::dropIfExists('maintenance_order_tasks');
        Schema::dropIfExists('maintenance_orders');
        Schema::dropIfExists('maintenance_plans');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('equipment_categories');
        Schema::dropIfExists('functional_locations');
    }
};
