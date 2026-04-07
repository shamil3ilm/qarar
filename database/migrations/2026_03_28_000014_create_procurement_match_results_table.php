<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_match_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained('procurement_goods_receipts')->nullOnDelete();
            $table->decimal('po_total', 15, 2)->default(0);
            $table->decimal('gr_total', 15, 2)->default(0);
            $table->decimal('bill_total', 15, 2)->default(0);
            $table->decimal('variance_amount', 15, 2)->default(0);
            $table->decimal('variance_pct', 8, 2)->default(0);
            $table->decimal('tolerance_pct', 8, 2)->default(0);
            $table->enum('match_status', [
                'matched',
                'po_qty_variance',
                'price_variance',
                'gr_missing',
                'passed_with_tolerance',
            ])->default('matched');
            $table->timestamp('matched_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'match_status'], 'proc_match_org_status_idx');
            $table->index('bill_id', 'proc_match_bill_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_match_results');
    }
};
