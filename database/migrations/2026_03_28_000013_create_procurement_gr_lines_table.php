<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_gr_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('procurement_goods_receipts')->cascadeOnDelete();
            $table->foreignId('po_line_id')->nullable()->constrained('purchase_order_lines')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('ordered_qty', 15, 3)->default(0);
            $table->decimal('received_qty', 15, 3)->default(0);
            $table->decimal('accepted_qty', 15, 3)->default(0);
            $table->decimal('rejected_qty', 15, 3)->default(0);
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('goods_receipt_id', 'proc_gr_lines_gr_id_idx');
            $table->index('product_id', 'proc_gr_lines_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_gr_lines');
    }
};
