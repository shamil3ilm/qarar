<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cycle_count_adjustments');
        Schema::dropIfExists('cycle_count_lines');
        Schema::dropIfExists('cycle_count_sessions');
        Schema::dropIfExists('cycle_count_plans');

        Schema::create('cycle_count_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('plan_name');
            $table->unsignedBigInteger('warehouse_id');
            $table->enum('count_frequency', ['A', 'B', 'C', 'custom']);
            $table->unsignedSmallInteger('products_per_day')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->enum('status', ['draft', 'active', 'paused', 'completed'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('warehouse_id', 'cc_plan_wh_fk')->references('id')->on('warehouses')->cascadeOnDelete();
        });

        Schema::create('cycle_count_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('warehouse_id');
            $table->date('session_date');
            $table->unsignedBigInteger('counted_by');
            $table->enum('status', ['open', 'in_progress', 'completed', 'posted'])->default('open');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plan_id', 'cc_sess_plan_fk')->references('id')->on('cycle_count_plans')->nullOnDelete();
            $table->foreign('warehouse_id', 'cc_sess_wh_fk')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('counted_by', 'cc_sess_usr_fk')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('cycle_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('cycle_count_session_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_location_id')->nullable();
            $table->decimal('system_quantity', 18, 4);
            $table->decimal('counted_quantity', 18, 4)->nullable();
            $table->decimal('variance_percentage', 8, 4)->nullable();
            $table->boolean('recount_required')->default(false);
            $table->enum('status', ['pending', 'counted', 'recounted', 'approved'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('cycle_count_session_id', 'cc_line_sess_fk')->references('id')->on('cycle_count_sessions')->cascadeOnDelete();
            $table->foreign('product_id', 'cc_line_prod_fk')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('warehouse_location_id', 'cc_line_loc_fk')->references('id')->on('warehouse_locations')->nullOnDelete();
            $table->foreign('approved_by', 'cc_line_appr_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('cycle_count_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('cycle_count_session_id');
            $table->unsignedBigInteger('stock_adjustment_id')->nullable();
            $table->unsignedBigInteger('posted_by');
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('cycle_count_session_id', 'cc_adj_sess_fk')->references('id')->on('cycle_count_sessions')->cascadeOnDelete();
            $table->foreign('stock_adjustment_id', 'cc_adj_sa_fk')->references('id')->on('stock_adjustments')->nullOnDelete();
            $table->foreign('posted_by', 'cc_adj_usr_fk')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_count_adjustments');
        Schema::dropIfExists('cycle_count_lines');
        Schema::dropIfExists('cycle_count_sessions');
        Schema::dropIfExists('cycle_count_plans');
    }
};
