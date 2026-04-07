<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('version_code', 20);
            $table->string('description', 200);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->enum('status', ['draft', 'active', 'frozen', 'archived'])->default('draft');
            $table->enum('costing_type', ['standard', 'actual', 'planned'])->default('standard');
            $table->string('currency_code', 3)->default('USD');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->unique(['organization_id', 'version_code'], 'cv_org_code_unique');
            $table->index(['organization_id', 'status'], 'cv_org_status_idx');
        });

        Schema::create('product_standard_costs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('costing_version_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->decimal('material_cost', 15, 4)->default(0);
            $table->decimal('labor_cost', 15, 4)->default(0);
            $table->decimal('overhead_cost', 15, 4)->default(0);
            $table->decimal('subcontracting_cost', 15, 4)->default(0);
            $table->decimal('total_standard_cost', 15, 4)->default(0);
            $table->decimal('cost_per_unit', 15, 4)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->unsignedBigInteger('bom_id')->nullable();
            $table->timestamps();

            $table->foreign('costing_version_id')->references('id')->on('costing_versions')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null');
            $table->foreign('bom_id')->references('id')->on('bom_templates')->onDelete('set null');
            $table->unique(['costing_version_id', 'product_id', 'variant_id'], 'psc_version_product_variant_unique');
            $table->index(['costing_version_id', 'product_id'], 'psc_version_product_idx');
        });

        Schema::create('cost_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('standard_cost_id');
            $table->enum('component_type', ['material', 'labor', 'overhead', 'subcontracting']);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 200);
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->timestamps();

            $table->foreign('standard_cost_id')->references('id')->on('product_standard_costs')->onDelete('cascade');
            $table->index(['standard_cost_id', 'component_type'], 'cc_sc_type_idx');
        });

        Schema::create('costing_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('costing_version_id');
            $table->date('run_date');
            $table->unsignedInteger('products_processed')->default(0);
            $table->unsignedInteger('products_failed')->default(0);
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('costing_version_id')->references('id')->on('costing_versions')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->index(['organization_id', 'run_date'], 'cr_org_date_idx');
        });

        Schema::create('cost_variances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('costing_version_id');
            $table->decimal('standard_material_cost', 15, 4)->default(0);
            $table->decimal('actual_material_cost', 15, 4)->default(0);
            $table->decimal('standard_labor_cost', 15, 4)->default(0);
            $table->decimal('actual_labor_cost', 15, 4)->default(0);
            $table->decimal('standard_overhead_cost', 15, 4)->default(0);
            $table->decimal('actual_overhead_cost', 15, 4)->default(0);
            $table->decimal('total_standard', 15, 4)->default(0);
            $table->decimal('total_actual', 15, 4)->default(0);
            $table->decimal('total_variance', 15, 4)->default(0);
            $table->decimal('variance_pct', 7, 2)->default(0);
            $table->smallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
            $table->foreign('costing_version_id')->references('id')->on('costing_versions')->onDelete('cascade');
            $table->unique(['work_order_id', 'costing_version_id'], 'cvar_wo_version_unique');
            $table->index(['organization_id', 'period_year', 'period_month'], 'cvar_org_period_idx');
        });

        Schema::create('wip_valuations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('work_order_id');
            $table->date('valuation_date');
            $table->decimal('completed_qty', 15, 4)->default(0);
            $table->decimal('wip_qty', 15, 4)->default(0);
            $table->decimal('material_wip', 15, 4)->default(0);
            $table->decimal('labor_wip', 15, 4)->default(0);
            $table->decimal('overhead_wip', 15, 4)->default(0);
            $table->decimal('total_wip', 15, 4)->default(0);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->unique(['work_order_id', 'valuation_date'], 'wip_wo_date_unique');
            $table->index(['organization_id', 'valuation_date'], 'wip_org_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wip_valuations');
        Schema::dropIfExists('cost_variances');
        Schema::dropIfExists('costing_runs');
        Schema::dropIfExists('cost_components');
        Schema::dropIfExists('product_standard_costs');
        Schema::dropIfExists('costing_versions');
    }
};
