<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('backorder_records');

        Schema::create('backorder_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete()->name('bor_so_fk');
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete()->name('bor_sol_fk');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->name('bor_product_fk');
            $table->decimal('original_quantity', 18, 4);
            $table->decimal('backordered_quantity', 18, 4);
            $table->decimal('fulfilled_quantity', 18, 4)->default(0);
            $table->enum('status', ['open', 'partially_fulfilled', 'fulfilled', 'cancelled'])->default('open');
            $table->date('original_delivery_date')->nullable();
            $table->date('rescheduled_delivery_date')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedTinyInteger('priority')->default(5);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status'], 'bor_org_status_idx');
            $table->index(['organization_id', 'product_id'], 'bor_org_product_idx');
            $table->index(['rescheduled_delivery_date'], 'bor_reschedule_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backorder_records');
    }
};
