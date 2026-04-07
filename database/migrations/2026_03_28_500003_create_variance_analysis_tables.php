<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('variance_analysis_items');
        Schema::dropIfExists('variance_analysis_runs');

        Schema::create('variance_analysis_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->enum('run_type', ['production_order', 'cost_center', 'project'])->default('production_order');
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->foreignId('run_by')
                ->nullable()
                ->constrained('users')
                ->name('var_run_by_fk');
            $table->dateTime('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'period', 'fiscal_year'], 'var_run_period_idx');
        });

        Schema::create('variance_analysis_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('vai_org_fk');
            $table->foreignId('variance_analysis_run_id')
                ->constrained('variance_analysis_runs')
                ->name('vai_run_fk');
            $table->string('reference_type', 50); // 'work_order', 'process_order', 'cost_center'
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('cost_element_id')
                ->nullable()
                ->constrained('cost_elements')
                ->name('vai_ce_fk');
            $table->enum('variance_category', [
                'price_variance',
                'quantity_variance',
                'efficiency_variance',
                'spending_variance',
                'resource_usage_variance',
                'remaining_input_variance',
                'output_price_variance',
                'mixed_price_variance',
            ]);
            $table->decimal('standard_cost', 18, 4)->default(0);
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->decimal('variance_amount', 18, 4)->default(0);
            $table->decimal('variance_percent', 8, 4)->default(0);
            $table->timestamps();

            $table->index(['variance_analysis_run_id'], 'vai_run_idx');
            $table->index(['reference_type', 'reference_id'], 'vai_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variance_analysis_items');
        Schema::dropIfExists('variance_analysis_runs');
    }
};
