<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------------
        // Gap 1: FI-GL Document Splitting
        // ----------------------------------------------------------------

        Schema::create('document_splitting_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('split_method', ['profit_center', 'segment', 'cost_center', 'business_area'])
                ->default('profit_center');
            $table->string('base_item_category', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(10);
            $table->timestamps();
            $table->index(['organization_id', 'is_active'], 'dsr_org_active_idx');
        });

        Schema::create('journal_entry_split_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->unsignedBigInteger('original_line_id')->nullable();
            $table->foreignId('profit_center_id')->nullable()->constrained('profit_centers')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->decimal('debit_amount', 15, 4)->default(0);
            $table->decimal('credit_amount', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->timestamps();
            $table->index(['journal_entry_id'], 'jesi_je_idx');
            $table->index(['profit_center_id'], 'jesi_pc_idx');
        });

        Schema::create('posting_validation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('rule_name', 100);
            $table->enum('rule_type', ['validation', 'substitution'])->default('validation');
            $table->string('trigger_event', 50)->default('on_save');
            $table->json('conditions');
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(10);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'is_active', 'rule_type'], 'pvr_org_active_type_idx');
        });

        // ----------------------------------------------------------------
        // Gap 2: CO-PA Plan Data & Actual vs Plan Variance
        // ----------------------------------------------------------------

        Schema::create('copa_plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('version_name', 100);
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'fiscal_year_id', 'version_name'], 'cpv_org_fy_name_unique');
        });

        Schema::create('copa_planned_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_version_id')->constrained('copa_plan_versions')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->integer('period');
            $table->foreignId('profit_center_id')->nullable()->constrained('profit_centers')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->decimal('planned_revenue', 15, 4)->default(0);
            $table->decimal('planned_cogs', 15, 4)->default(0);
            $table->decimal('planned_gross_profit', 15, 4)->default(0);
            $table->decimal('planned_overhead', 15, 4)->default(0);
            $table->decimal('planned_net_profit', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->timestamps();
            $table->index(['plan_version_id', 'period'], 'cpli_version_period_idx');
            $table->index(['organization_id', 'profit_center_id'], 'cpli_org_pc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copa_planned_line_items');
        Schema::dropIfExists('copa_plan_versions');
        Schema::dropIfExists('posting_validation_rules');
        Schema::dropIfExists('journal_entry_split_items');
        Schema::dropIfExists('document_splitting_rules');
    }
};
