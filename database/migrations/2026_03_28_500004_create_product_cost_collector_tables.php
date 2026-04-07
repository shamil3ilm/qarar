<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('product_cost_collector_items');
        Schema::dropIfExists('product_cost_collectors');

        Schema::create('product_cost_collectors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('product_id')
                ->constrained('products')
                ->name('pcc_product_fk');
            // production_lines table may not exist; use nullable unsignedBigInteger without FK
            $table->unsignedBigInteger('production_line_id')->nullable();
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->decimal('standard_cost_total', 18, 4)->default(0);
            $table->decimal('actual_cost_total', 18, 4)->default(0);
            $table->decimal('total_variance', 18, 4)->default(0);
            $table->decimal('quantity_produced', 18, 4)->default(0);
            $table->decimal('cost_per_unit_standard', 18, 4)->default(0);
            $table->decimal('cost_per_unit_actual', 18, 4)->default(0);
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'product_id', 'production_line_id', 'period', 'fiscal_year'],
                'pcc_org_prod_line_period_fy_unq'
            );
        });

        Schema::create('product_cost_collector_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('pcci_org_fk');
            $table->foreignId('product_cost_collector_id')
                ->constrained('product_cost_collectors')
                ->name('pcci_pcc_fk');
            $table->foreignId('cost_element_id')
                ->nullable()
                ->constrained('cost_elements')
                ->name('pcci_ce_fk');
            $table->enum('cost_category', ['material', 'labor', 'overhead', 'other'])->default('material');
            $table->decimal('standard_cost', 18, 4)->default(0);
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->decimal('variance', 18, 4)->default(0);
            $table->timestamps();

            $table->index(['product_cost_collector_id'], 'pcci_pcc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_cost_collector_items');
        Schema::dropIfExists('product_cost_collectors');
    }
};
