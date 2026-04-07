<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // cost_elements — Primary (linked to GL account) and Secondary (internal allocation)
        Schema::create('cost_elements', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->enum('element_type', ['primary', 'secondary']); // primary=GL mapped, secondary=internal
            $table->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->enum('cost_element_category', ['general', 'depreciation', 'imputed', 'revenue', 'internal_settlement'])->default('general');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code'], 'ce_org_code_unique');
        });

        // activity_types — defines work types performed by cost centers
        Schema::create('activity_types', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('unit_of_measure', 20)->default('HR'); // HR=hours, PC=pieces
            $table->foreignId('cost_element_id')->nullable()->constrained('cost_elements')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code'], 'at_org_code_unique');
        });

        // activity_rates — planned/actual rates per cost center per fiscal period
        Schema::create('activity_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_type_id')->constrained('activity_types')->cascadeOnDelete();
            $table->foreignId('cost_center_id')->constrained('cost_centers')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->integer('period')->default(1); // 1-12
            $table->decimal('planned_rate', 15, 4)->default(0);
            $table->decimal('actual_rate', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->timestamps();
            $table->unique(['activity_type_id', 'cost_center_id', 'fiscal_year_id', 'period'], 'ar_unique_rate');
        });

        // internal_orders — CO internal orders (IO) for short-term cost collection
        Schema::create('internal_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('order_number', 30);
            $table->string('description', 255);
            $table->enum('order_type', ['overhead', 'investment', 'accrual', 'statistical'])->default('overhead');
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget_amount', 15, 4)->default(0);
            $table->decimal('committed_amount', 15, 4)->default(0);
            $table->decimal('actual_amount', 15, 4)->default(0);
            $table->enum('status', ['created', 'released', 'technically_completed', 'closed'])->default('created');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'order_number'], 'io_org_number_unique');
        });

        // internal_order_settlements — settlement rules: distribute IO costs to receivers
        Schema::create('internal_order_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('internal_order_id')->constrained('internal_orders')->cascadeOnDelete();
            $table->enum('receiver_type', ['cost_center', 'gl_account', 'project_wbs', 'profit_center']);
            $table->unsignedBigInteger('receiver_id');
            $table->decimal('settlement_percentage', 5, 2);
            $table->timestamps();
            $table->index(['internal_order_id'], 'ios_order_idx');
        });

        // copa_dimensions — CO-PA profitability analysis characteristic values
        Schema::create('copa_dimensions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('dimension_type', 50); // product, customer, region, sales_channel, material_group
            $table->string('dimension_value', 100);
            $table->string('dimension_label', 200)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'dimension_type'], 'copa_dim_org_type_idx');
        });

        // copa_line_items — actual CO-PA postings
        Schema::create('copa_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->integer('period');
            $table->date('posting_date');
            $table->string('source_document_type', 30); // invoice, credit_note, settlement
            $table->unsignedBigInteger('source_document_id')->nullable();
            $table->foreignId('profit_center_id')->nullable()->constrained('profit_centers')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->decimal('revenue', 15, 4)->default(0);
            $table->decimal('cogs', 15, 4)->default(0);
            $table->decimal('gross_profit', 15, 4)->default(0);
            $table->decimal('overhead_allocated', 15, 4)->default(0);
            $table->decimal('net_profit', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->timestamps();
            $table->index(['organization_id', 'fiscal_year_id', 'period'], 'copa_li_org_fy_period_idx');
            $table->index(['organization_id', 'posting_date'], 'copa_li_org_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copa_line_items');
        Schema::dropIfExists('copa_dimensions');
        Schema::dropIfExists('internal_order_settlements');
        Schema::dropIfExists('internal_orders');
        Schema::dropIfExists('activity_rates');
        Schema::dropIfExists('activity_types');
        Schema::dropIfExists('cost_elements');
    }
};
