<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('gr_number', 30)->nullable();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->enum('status', ['draft', 'confirmed', 'quality_hold'])->default('draft');
            $table->string('supplier_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'proc_gr_org_status_idx');
            $table->index(['organization_id', 'purchase_order_id'], 'proc_gr_org_po_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_goods_receipts');
    }
};
