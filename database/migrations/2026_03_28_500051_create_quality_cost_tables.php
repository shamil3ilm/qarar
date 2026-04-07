<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('quality_cost_entries');

        Schema::create('quality_cost_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->enum('cost_category', ['prevention', 'appraisal', 'internal_failure', 'external_failure'])->default('internal_failure');
            $table->string('cost_subcategory', 100)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'qce_product_fk')->references('id')->on('products');
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('amount', 18, 4);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->foreign('recorded_by', 'qce_recorded_by_fk')->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'period', 'fiscal_year'], 'qce_org_period_fy_idx');
            $table->index(['organization_id', 'cost_category'], 'qce_org_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_cost_entries');
    }
};
