<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('profitability_segment_values');
        Schema::dropIfExists('profitability_segments');

        Schema::create('profitability_segments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('segment_name', 100);
            $table->foreignId('customer_group_id')
                ->nullable()
                ->constrained('customer_groups')
                ->name('ps_cg_fk');
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->name('ps_prod_fk');
            $table->string('region', 100)->nullable();
            $table->string('sales_channel', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id'], 'ps_org_idx');
        });

        Schema::create('profitability_segment_values', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('psv_org_fk');
            $table->foreignId('profitability_segment_id')
                ->constrained('profitability_segments')
                ->name('psv_seg_fk');
            $table->foreignId('copa_dimension_id')
                ->nullable()
                ->constrained('copa_dimensions')
                ->name('psv_copa_dim_fk');
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('revenue', 18, 4)->default(0);
            $table->decimal('cost_of_sales', 18, 4)->default(0);
            $table->decimal('gross_margin', 18, 4)->default(0);
            $table->decimal('overhead_costs', 18, 4)->default(0);
            $table->decimal('net_margin', 18, 4)->default(0);
            $table->decimal('quantity_sold', 18, 4)->default(0);
            $table->timestamps();

            $table->index(['profitability_segment_id', 'period', 'fiscal_year'], 'psv_seg_period_fy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profitability_segment_values');
        Schema::dropIfExists('profitability_segments');
    }
};
