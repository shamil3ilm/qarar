<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mrp_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->dateTime('run_date');
            $table->integer('planning_horizon_days')->default(30);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->integer('total_products_analyzed')->default(0);
            $table->integer('total_planned_orders')->default(0);
            $table->text('error_message')->nullable();
            $table->foreignId('run_by')->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['organization_id', 'status']);
        });

        Schema::create('mrp_demand_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mrp_run_id')->constrained('mrp_runs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->enum('source_type', ['sales_order', 'forecast', 'safety_stock', 'bom'])->default('sales_order');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('required_date');
            $table->decimal('required_quantity', 12, 4);
            $table->timestamps();

            $table->index('mrp_run_id');
            $table->index('product_id');
        });

        Schema::create('mrp_planned_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('mrp_run_id')->constrained('mrp_runs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->enum('order_type', ['purchase', 'production', 'transfer'])->default('purchase');
            $table->decimal('planned_quantity', 12, 4);
            $table->date('planned_start_date');
            $table->date('planned_end_date');
            $table->enum('status', ['planned', 'firmed', 'converted', 'cancelled'])->default('planned');
            $table->unsignedBigInteger('source_demand_id')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('converted_to_type')->nullable();
            $table->unsignedBigInteger('converted_to_id')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['product_id', 'status']);
        });

        Schema::create('demand_forecasts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->date('forecast_date');
            $table->decimal('forecast_quantity', 12, 4);
            $table->decimal('actual_quantity', 12, 4)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'product_id', 'forecast_date']);
            $table->index(['organization_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_forecasts');
        Schema::dropIfExists('mrp_planned_orders');
        Schema::dropIfExists('mrp_demand_items');
        Schema::dropIfExists('mrp_runs');
    }
};
