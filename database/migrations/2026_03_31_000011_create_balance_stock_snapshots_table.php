<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop both tables first so this migration is safely re-runnable
        // (handles the case where a previous partial run left one table behind)
        Schema::dropIfExists('stock_level_snapshots');
        Schema::dropIfExists('account_balance_snapshots');

        Schema::create('account_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->decimal('balance', 20, 4)->default(0);
            $table->decimal('debit_total', 20, 4)->default(0);
            $table->decimal('credit_total', 20, 4)->default(0);
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->unique(['organization_id', 'account_id']);
            $table->index(['organization_id', 'computed_at']);
        });

        Schema::create('stock_level_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('quantity_on_hand', 20, 4)->default(0);
            $table->decimal('quantity_reserved', 20, 4)->default(0);
            $table->decimal('quantity_available', 20, 4)->default(0);
            $table->decimal('reorder_point', 20, 4)->default(0);
            $table->boolean('is_low_stock')->default(false);
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->unique(['organization_id', 'product_id', 'warehouse_id'], 'sls_org_product_warehouse_unique');
            $table->index(['organization_id', 'is_low_stock'], 'sls_org_low_stock_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_level_snapshots');
        Schema::dropIfExists('account_balance_snapshots');
    }
};
