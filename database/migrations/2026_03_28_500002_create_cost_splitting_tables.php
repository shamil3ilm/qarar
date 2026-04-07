<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cost_splitting_results');
        Schema::dropIfExists('cost_splitting_rules');

        Schema::create('cost_splitting_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('cost_center_id')
                ->constrained('cost_centers')
                ->name('csr_cc_fk');
            $table->foreignId('cost_element_id')
                ->nullable()
                ->constrained('cost_elements')
                ->name('csr_ce_fk');
            $table->decimal('fixed_percentage', 5, 2);
            $table->decimal('variable_percentage', 5, 2);
            $table->enum('splitting_basis', ['activity_quantity', 'capacity_utilization', 'manual'])->default('manual');
            $table->boolean('is_active')->default(true);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'cost_center_id'], 'csr_org_cc_idx');
        });

        Schema::create('cost_splitting_results', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('csres_org_fk');
            $table->foreignId('cost_splitting_rule_id')
                ->constrained('cost_splitting_rules')
                ->name('csres_rule_fk');
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('total_cost', 18, 4);
            $table->decimal('fixed_cost', 18, 4);
            $table->decimal('variable_cost', 18, 4);
            $table->dateTime('run_at');
            $table->timestamps();

            $table->index(['organization_id', 'period', 'fiscal_year'], 'csres_org_period_fy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_splitting_results');
        Schema::dropIfExists('cost_splitting_rules');
    }
};
