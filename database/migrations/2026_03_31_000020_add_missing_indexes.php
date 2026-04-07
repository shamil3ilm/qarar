<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── invoices ──────────────────────────────────────────────────────────
        // compliance_status — queried by RetryComplianceSubmission job
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('compliance_status', 'inv_compliance_status_idx');
        });

        // ── invoice_lines ─────────────────────────────────────────────────────
        // product_id — cross-product sales analysis
        // (invoice_id already has an index from the original migration)
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->index('product_id', 'invl_product_id_idx');
        });

        // ── purchase_orders ───────────────────────────────────────────────────
        // Composite date lookups used in reporting and delivery tracking
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index(['organization_id', 'order_date'], 'po_org_order_date_idx');
            $table->index(['organization_id', 'expected_delivery_date'], 'po_org_exp_delivery_idx');
        });

        // ── purchase_order_lines ─────────────────────────────────────────────
        // This table has ZERO indexes — not even purchase_order_id
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->index('purchase_order_id', 'pol_purchase_order_id_idx');
            $table->index('product_id', 'pol_product_id_idx');
        });

        // ── bill_lines ────────────────────────────────────────────────────────
        // bill_id FK has no covering index
        Schema::table('bill_lines', function (Blueprint $table) {
            $table->index('bill_id', 'bl_bill_id_idx');
        });

        // ── payslips ──────────────────────────────────────────────────────────
        // ['organization_id', 'status'] is already present in the original migration.
        // Add standalone employee+status for YTD queries:
        //   WHERE employee_id = ? AND status IN (...)
        Schema::table('payslips', function (Blueprint $table) {
            $table->index(['employee_id', 'status'], 'ps_employee_status_idx');
        });

        // ── stock_adjustments ─────────────────────────────────────────────────
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->index(['organization_id', 'status'], 'sa_org_status_idx');
            $table->index(['organization_id', 'warehouse_id'], 'sa_org_warehouse_idx');
            $table->index(['organization_id', 'adjustment_date'], 'sa_org_date_idx');
        });

        // ── stock_adjustment_lines ────────────────────────────────────────────
        Schema::table('stock_adjustment_lines', function (Blueprint $table) {
            $table->index('stock_adjustment_id', 'sal_adjustment_id_idx');
        });

        // ── stock_transfers ────────────────────────────────────────────────────
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->index(['organization_id', 'status'], 'st_org_status_idx');
            $table->index(['organization_id', 'transfer_date'], 'st_org_date_idx');
            $table->index(['organization_id', 'from_warehouse_id'], 'st_org_from_wh_idx');
            $table->index(['organization_id', 'to_warehouse_id'], 'st_org_to_wh_idx');
        });

        // ── stock_transfer_lines ───────────────────────────────────────────────
        Schema::table('stock_transfer_lines', function (Blueprint $table) {
            $table->index('stock_transfer_id', 'stl_transfer_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropIndex('inv_compliance_status_idx'));
        Schema::table('invoice_lines', fn (Blueprint $t) => $t->dropIndex('invl_product_id_idx'));
        Schema::table('purchase_orders', function (Blueprint $t) {
            $t->dropIndex('po_org_order_date_idx');
            $t->dropIndex('po_org_exp_delivery_idx');
        });
        Schema::table('purchase_order_lines', function (Blueprint $t) {
            $t->dropIndex('pol_purchase_order_id_idx');
            $t->dropIndex('pol_product_id_idx');
        });
        Schema::table('bill_lines', fn (Blueprint $t) => $t->dropIndex('bl_bill_id_idx'));
        Schema::table('payslips', fn (Blueprint $t) => $t->dropIndex('ps_employee_status_idx'));
        Schema::table('stock_adjustments', function (Blueprint $t) {
            $t->dropIndex('sa_org_status_idx');
            $t->dropIndex('sa_org_warehouse_idx');
            $t->dropIndex('sa_org_date_idx');
        });
        Schema::table('stock_adjustment_lines', fn (Blueprint $t) => $t->dropIndex('sal_adjustment_id_idx'));
        Schema::table('stock_transfers', function (Blueprint $t) {
            $t->dropIndex('st_org_status_idx');
            $t->dropIndex('st_org_date_idx');
            $t->dropIndex('st_org_from_wh_idx');
            $t->dropIndex('st_org_to_wh_idx');
        });
        Schema::table('stock_transfer_lines', fn (Blueprint $t) => $t->dropIndex('stl_transfer_id_idx'));
    }
};
