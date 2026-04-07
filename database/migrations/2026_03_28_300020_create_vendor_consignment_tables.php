<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('vendor_consignment_settlements');
        Schema::dropIfExists('vendor_consignment_withdrawals');
        Schema::dropIfExists('vendor_consignment_receipts');
        Schema::dropIfExists('vendor_consignment_stocks');

        Schema::create('vendor_consignment_stocks', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('warehouse_location_id')->nullable();
            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            $table->decimal('quantity_reserved', 18, 4)->default(0);
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->decimal('vendor_price', 18, 4);
            $table->string('currency_code', 3);
            $table->dateTime('last_movement_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'vcs_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('vendor_id', 'vcs_vendor_fk')
                ->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('product_id', 'vcs_product_fk')
                ->references('id')->on('products')->onDelete('cascade');
            $table->foreign('warehouse_id', 'vcs_warehouse_fk')
                ->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('warehouse_location_id', 'vcs_wh_loc_fk')
                ->references('id')->on('warehouse_locations')->onDelete('set null');
            $table->foreign('unit_id', 'vcs_unit_fk')
                ->references('id')->on('units_of_measure')->onDelete('set null');

            $table->unique(
                ['organization_id', 'vendor_id', 'product_id', 'warehouse_id'],
                'vcs_org_vendor_product_wh_unique'
            );
            $table->index(['vendor_id', 'product_id'], 'vcs_vendor_product_idx');
        });

        Schema::create('vendor_consignment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('vendor_consignment_stock_id');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->date('receipt_date');
            $table->decimal('quantity_received', 18, 4);
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->string('vendor_delivery_note', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'vcr_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('vendor_consignment_stock_id', 'vcr_stock_fk')
                ->references('id')->on('vendor_consignment_stocks')->onDelete('cascade');
            $table->foreign('purchase_order_id', 'vcr_po_fk')
                ->references('id')->on('purchase_orders')->onDelete('set null');
            $table->foreign('unit_id', 'vcr_unit_fk')
                ->references('id')->on('units_of_measure')->onDelete('set null');
            $table->foreign('created_by', 'vcr_created_by_fk')
                ->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('vendor_consignment_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('vendor_consignment_stock_id');
            $table->date('withdrawal_date');
            $table->decimal('quantity_withdrawn', 18, 4);
            $table->string('withdrawal_type', 30); // production/sales/transfer/scrapping
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'vcw_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('vendor_consignment_stock_id', 'vcw_stock_fk')
                ->references('id')->on('vendor_consignment_stocks')->onDelete('cascade');
            $table->foreign('unit_id', 'vcw_unit_fk')
                ->references('id')->on('units_of_measure')->onDelete('set null');
            $table->foreign('created_by', 'vcw_created_by_fk')
                ->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('vendor_consignment_settlements', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('vendor_id');
            $table->date('settlement_period_from');
            $table->date('settlement_period_to');
            $table->decimal('total_quantity', 18, 4);
            $table->decimal('total_value', 18, 4);
            $table->string('currency_code', 3);
            $table->string('status', 20)->default('draft'); // draft/submitted/paid
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->dateTime('settled_at')->nullable();
            $table->unsignedBigInteger('settled_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'vcse_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('vendor_id', 'vcse_vendor_fk')
                ->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('bill_id', 'vcse_bill_fk')
                ->references('id')->on('bills')->onDelete('set null');
            $table->foreign('settled_by', 'vcse_settled_by_fk')
                ->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'settlement_period_from'], 'vcse_status_period_idx');
            $table->index(['organization_id', 'vendor_id'], 'vcse_org_vendor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_consignment_settlements');
        Schema::dropIfExists('vendor_consignment_withdrawals');
        Schema::dropIfExists('vendor_consignment_receipts');
        Schema::dropIfExists('vendor_consignment_stocks');
    }
};
