<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontract_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('order_number', 30);
            $table->unsignedBigInteger('contact_id');
            $table->enum('status', ['draft', 'sent', 'material_transferred', 'in_process', 'received', 'closed', 'cancelled'])->default('draft');
            $table->date('issued_date')->nullable();
            $table->date('expected_receipt_date')->nullable();
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('service_charge', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('restrict');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->unique(['organization_id', 'order_number'], 'sco_org_number_unique');
            $table->index(['organization_id', 'status'], 'sco_org_status_idx');
        });

        Schema::create('subcontract_order_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->decimal('ordered_quantity', 15, 4);
            $table->decimal('received_quantity', 15, 4)->default(0);
            $table->unsignedBigInteger('unit_id');
            $table->decimal('unit_service_charge', 15, 4)->default(0);
            $table->decimal('total_service_charge', 15, 4)->default(0);
            $table->decimal('scrap_quantity', 15, 4)->default(0);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('subcontract_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('units_of_measure');
            $table->index('order_id', 'scol_order_idx');
        });

        Schema::create('subcontract_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->decimal('required_quantity', 15, 4);
            $table->decimal('transferred_quantity', 15, 4)->default(0);
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('subcontract_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('units_of_measure');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->index('order_id', 'sccomp_order_idx');
        });

        Schema::create('subcontract_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('order_id');
            $table->date('transfer_date');
            $table->enum('transfer_type', ['outward', 'inward']);
            $table->unsignedBigInteger('warehouse_id');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('subcontract_orders')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('stock_movement_id')->references('id')->on('stock_movements')->onDelete('set null');
            $table->index(['order_id', 'transfer_type'], 'sct_order_type_idx');
        });

        Schema::create('subcontract_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('component_line_id')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->unsignedBigInteger('unit_id');
            $table->string('batch_number', 100)->nullable();
            $table->timestamps();

            $table->foreign('transfer_id')->references('id')->on('subcontract_transfers')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null');
            $table->foreign('component_line_id')->references('id')->on('subcontract_components')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('units_of_measure');
            $table->index('transfer_id', 'sctl_transfer_idx');
        });

        Schema::create('subcontract_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('order_id');
            $table->date('receipt_date');
            $table->unsignedBigInteger('warehouse_id');
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('subcontract_orders')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->index('order_id', 'scr_order_idx');
        });

        Schema::create('subcontract_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receipt_id');
            $table->unsignedBigInteger('order_line_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity_received', 15, 4);
            $table->decimal('quantity_rejected', 15, 4)->default(0);
            $table->unsignedBigInteger('unit_id');
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->foreign('receipt_id')->references('id')->on('subcontract_receipts')->onDelete('cascade');
            $table->foreign('order_line_id')->references('id')->on('subcontract_order_lines')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('unit_id')->references('id')->on('units_of_measure');
            $table->index('receipt_id', 'scrl_receipt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontract_receipt_lines');
        Schema::dropIfExists('subcontract_receipts');
        Schema::dropIfExists('subcontract_transfer_lines');
        Schema::dropIfExists('subcontract_transfers');
        Schema::dropIfExists('subcontract_components');
        Schema::dropIfExists('subcontract_order_lines');
        Schema::dropIfExists('subcontract_orders');
    }
};
