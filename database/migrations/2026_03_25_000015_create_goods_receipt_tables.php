<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('gr_number', 30);
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->date('gr_date');
            $table->unsignedBigInteger('warehouse_id');
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->unique(['organization_id', 'gr_number'], 'goods_receipts_org_number_unique');
            $table->index(['organization_id', 'status'], 'goods_receipts_org_status_idx');
            $table->index(['organization_id', 'gr_date'], 'goods_receipts_org_date_idx');
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gr_id');
            $table->unsignedBigInteger('po_line_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('description', 500);
            $table->decimal('quantity_ordered', 15, 4);
            $table->decimal('quantity_received', 15, 4);
            $table->decimal('quantity_rejected', 15, 4)->default(0);
            $table->unsignedBigInteger('unit_id');
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('total_cost', 15, 4);
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->foreign('gr_id')->references('id')->on('goods_receipts')->onDelete('cascade');
            $table->foreign('po_line_id')->references('id')->on('purchase_order_lines')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('unit_id')->references('id')->on('units_of_measure');
            $table->foreign('location_id')->references('id')->on('warehouse_locations')->nullOnDelete();

            $table->index('gr_id', 'gr_lines_gr_id_idx');
            $table->index('po_line_id', 'gr_lines_po_line_id_idx');
        });

        Schema::create('three_way_match_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('bill_id');
            $table->unsignedBigInteger('bill_line_id')->nullable();
            $table->unsignedBigInteger('po_line_id')->nullable();
            $table->unsignedBigInteger('gr_line_id')->nullable();
            $table->decimal('po_quantity', 15, 4)->nullable();
            $table->decimal('gr_quantity', 15, 4)->nullable();
            $table->decimal('invoice_quantity', 15, 4)->nullable();
            $table->decimal('po_unit_price', 15, 4)->nullable();
            $table->decimal('invoice_unit_price', 15, 4)->nullable();
            $table->boolean('quantity_match')->default(false);
            $table->boolean('price_match')->default(false);
            $table->enum('match_status', ['matched', 'quantity_variance', 'price_variance', 'missing_gr', 'pending'])->default('pending');
            $table->decimal('variance_amount', 15, 4)->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('bill_id')->references('id')->on('bills')->onDelete('cascade');
            $table->foreign('po_line_id')->references('id')->on('purchase_order_lines')->nullOnDelete();
            $table->foreign('gr_line_id')->references('id')->on('goods_receipt_lines')->nullOnDelete();

            $table->index(['organization_id', 'bill_id'], 'twm_org_bill_idx');
            $table->index(['organization_id', 'match_status'], 'twm_org_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('three_way_match_results');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
    }
};
