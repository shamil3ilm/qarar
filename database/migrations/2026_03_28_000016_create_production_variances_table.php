<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_variances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('cost_version_id')->nullable()->constrained('cost_versions')->nullOnDelete();
            $table->enum('variance_type', ['material', 'labour', 'overhead', 'yield'])->default('material');
            $table->decimal('standard_cost', 15, 4)->default(0);
            $table->decimal('actual_cost', 15, 4)->default(0);
            $table->decimal('variance_amount', 15, 4)->default(0);
            $table->decimal('variance_pct', 8, 2)->default(0);
            $table->date('period_date');
            $table->boolean('posted_to_gl')->default(false);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'period_date'], 'prod_var_org_period_idx');
            $table->index(['organization_id', 'variance_type'], 'prod_var_org_type_idx');
            $table->index('work_order_id', 'prod_var_wo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_variances');
    }
};
