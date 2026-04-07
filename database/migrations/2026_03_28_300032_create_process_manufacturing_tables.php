<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('process_order_resources');
        Schema::dropIfExists('process_order_phases');
        Schema::dropIfExists('process_orders');
        Schema::dropIfExists('recipe_resources');
        Schema::dropIfExists('recipe_phases');
        Schema::dropIfExists('recipes');

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('recipe_code', 30);
            $table->string('name');
            $table->decimal('base_quantity', 18, 4);
            $table->foreignId('base_unit_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->string('recipe_type', 20)->default('master');
            $table->date('validity_from');
            $table->date('validity_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'recipe_code'],
                'recipes_org_code_unique'
            );
            $table->index(['product_id', 'is_active'], 'recipes_product_active_idx');
        });

        Schema::create('recipe_phases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->unsignedInteger('phase_number');
            $table->string('name');
            $table->text('operation_description')->nullable();
            $table->string('resource_type', 20);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->decimal('duration_hours', 8, 2);
            $table->decimal('temperature', 6, 2)->nullable();
            $table->decimal('pressure', 6, 2)->nullable();
            $table->unsignedInteger('agitation_rpm')->nullable();
            $table->timestamps();
        });

        Schema::create('recipe_resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('recipe_phase_id')
                ->nullable()
                ->constrained('recipe_phases')
                ->nullOnDelete();
            $table->foreignId('material_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->boolean('is_co_product')->default(false);
            $table->boolean('is_by_product')->default(false);
            $table->timestamps();
        });

        Schema::create('process_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 30);
            $table->decimal('planned_quantity', 18, 4);
            $table->decimal('actual_quantity', 18, 4)->nullable();
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->string('batch_number', 50)->nullable();
            $table->dateTime('planned_start');
            $table->dateTime('planned_finish');
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_finish')->nullable();
            $table->string('status', 20)->default('created');
            $table->foreignId('production_version_id')
                ->nullable()
                ->constrained('production_versions')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'order_number'],
                'proc_ord_org_number_unique'
            );
            $table->index(['product_id', 'status'], 'proc_ord_product_status_idx');
            $table->index(['status', 'planned_start'], 'proc_ord_status_start_idx');
        });

        Schema::create('process_order_phases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('process_order_id')
                ->constrained('process_orders')
                ->cascadeOnDelete();
            $table->foreignId('recipe_phase_id')
                ->nullable()
                ->constrained('recipe_phases')
                ->nullOnDelete();
            $table->unsignedInteger('phase_number');
            $table->string('name');
            $table->string('status', 20)->default('pending');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('actual_temperature', 6, 2)->nullable();
            $table->decimal('actual_pressure', 6, 2)->nullable();
            $table->unsignedInteger('actual_duration_minutes')->nullable();
            $table->text('operator_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('process_order_resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('process_order_id')
                ->constrained('process_orders')
                ->cascadeOnDelete();
            $table->foreignId('recipe_resource_id')
                ->nullable()
                ->constrained('recipe_resources')
                ->nullOnDelete();
            $table->foreignId('material_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->decimal('planned_quantity', 18, 4);
            $table->decimal('actual_quantity', 18, 4)->nullable();
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_order_resources');
        Schema::dropIfExists('process_order_phases');
        Schema::dropIfExists('process_orders');
        Schema::dropIfExists('recipe_resources');
        Schema::dropIfExists('recipe_phases');
        Schema::dropIfExists('recipes');
    }
};
