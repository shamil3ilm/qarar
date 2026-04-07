<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_costs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('cost_version_id')->nullable()->constrained('costing_versions')->nullOnDelete();
            $table->string('cost_type', 30)->default('standard')->comment('standard, actual, planned');
            $table->decimal('material_cost', 18, 4)->default(0);
            $table->decimal('labour_cost', 18, 4)->default(0);
            $table->decimal('overhead_cost', 18, 4)->default(0);
            $table->decimal('subcontracting_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('costed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'product_id']);
            $table->index(['product_id', 'cost_type', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_costs');
    }
};
