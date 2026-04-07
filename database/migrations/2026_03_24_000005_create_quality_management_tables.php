<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quality Plans
        Schema::create('quality_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->enum('inspection_stage', [
                'goods_receipt',
                'production',
                'pre_shipment',
                'in_process',
                'final',
            ])->default('goods_receipt');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'product_id']);
        });

        // Quality Plan Characteristics
        Schema::create('quality_plan_characteristics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_plan_id')->constrained('quality_plans')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('inspection_method')->nullable();
            $table->string('measurement_unit')->nullable();
            $table->decimal('lower_limit', 12, 4)->nullable();
            $table->decimal('upper_limit', 12, 4)->nullable();
            $table->decimal('target_value', 12, 4)->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('quality_plan_id');
        });

        // Inspection Lots
        Schema::create('inspection_lots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('lot_number');
            $table->foreignId('quality_plan_id')->nullable()->constrained('quality_plans')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->enum('source_type', [
                'purchase_order',
                'production',
                'transfer',
                'manual',
            ])->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('quantity', 12, 4);
            $table->decimal('inspected_quantity', 12, 4)->default(0);
            $table->decimal('accepted_quantity', 12, 4)->default(0);
            $table->decimal('rejected_quantity', 12, 4)->default(0);
            $table->enum('status', [
                'pending',
                'in_inspection',
                'accepted',
                'rejected',
                'partial_accept',
            ])->default('pending');
            $table->date('inspection_date')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'lot_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['product_id', 'status']);
        });

        // Inspection Results
        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_lot_id')->constrained('inspection_lots')->cascadeOnDelete();
            $table->foreignId('quality_plan_characteristic_id')
                ->nullable()
                ->constrained('quality_plan_characteristics')
                ->nullOnDelete();
            $table->string('characteristic_name');
            $table->decimal('measured_value', 12, 4)->nullable();
            $table->string('text_result')->nullable();
            $table->boolean('is_conforming')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index('inspection_lot_id');
        });

        // Quality Notifications
        Schema::create('quality_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('notification_number');
            $table->enum('notification_type', [
                'defect',
                'complaint',
                'improvement',
                'deviation',
            ])->default('defect');
            $table->enum('source_type', [
                'inspection_lot',
                'customer',
                'supplier',
                'internal',
            ])->default('internal');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', [
                'open',
                'in_progress',
                'resolved',
                'closed',
            ])->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->text('preventive_action')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'notification_number']);
            $table->index(['organization_id', 'status', 'priority']);
        });

        // Defect Records
        Schema::create('defect_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_notification_id')
                ->constrained('quality_notifications')
                ->cascadeOnDelete();
            $table->string('defect_type');
            $table->string('defect_code')->nullable();
            $table->integer('quantity')->default(1);
            $table->enum('severity', ['minor', 'major', 'critical'])->default('minor');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();

            $table->index('quality_notification_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_records');
        Schema::dropIfExists('quality_notifications');
        Schema::dropIfExists('inspection_results');
        Schema::dropIfExists('inspection_lots');
        Schema::dropIfExists('quality_plan_characteristics');
        Schema::dropIfExists('quality_plans');
    }
};
