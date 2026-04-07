<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds FK constraints that were missing from earlier migrations.
 *
 * All _id columns in this migration are non-polymorphic (they have no
 * companion _type column), so a database-level FK is both safe and correct.
 *
 * Polymorphic _id columns (auditable_id, attachable_id, subject_id,
 * commentable_id, notifiable_id, source_id with _type companion, etc.)
 * cannot have DB-level FK constraints by design — they are intentionally
 * left unconstrained.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------------
        // journal_entry_lines
        // contact_id links a GL line to the customer or supplier it applies to
        // ----------------------------------------------------------------
        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->foreign('contact_id', 'jel_contact_fk')
                ->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('cost_center_id', 'jel_cost_center_fk')
                ->references('id')->on('cost_centers')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // invoices
        // ----------------------------------------------------------------
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreign('quotation_id', 'inv_quotation_fk')
                ->references('id')->on('quotations')->nullOnDelete();
            $table->foreign('sales_order_id', 'inv_sales_order_fk')
                ->references('id')->on('sales_orders')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // work_orders — link back to the sales order that triggered them
        // ----------------------------------------------------------------
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->foreign('sales_order_id', 'wo_sales_order_fk')
                ->references('id')->on('sales_orders')->nullOnDelete();
            $table->foreign('sales_order_line_id', 'wo_sales_order_line_fk')
                ->references('id')->on('sales_order_lines')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // production_logs / material_transactions
        // stock_movement_id records which inventory movement was created
        // ----------------------------------------------------------------
        Schema::table('production_logs', function (Blueprint $table): void {
            $table->foreign('stock_movement_id', 'prod_log_stock_movement_fk')
                ->references('id')->on('stock_movements')->nullOnDelete();
        });

        Schema::table('material_transactions', function (Blueprint $table): void {
            $table->foreign('stock_movement_id', 'mat_txn_stock_movement_fk')
                ->references('id')->on('stock_movements')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // scheduled_emails — link to the sent email log record
        // ----------------------------------------------------------------
        Schema::table('scheduled_emails', function (Blueprint $table): void {
            $table->foreign('email_log_id', 'sched_email_log_fk')
                ->references('id')->on('email_logs')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // bank_transactions — self-referential: matched pair
        // ----------------------------------------------------------------
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->foreign('matched_transaction_id', 'bank_txn_matched_pair_fk')
                ->references('id')->on('bank_transactions')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // CRM — leads and opportunities cross-reference each other
        // ----------------------------------------------------------------
        Schema::table('leads', function (Blueprint $table): void {
            $table->foreign('converted_opportunity_id', 'lead_converted_opp_fk')
                ->references('id')->on('opportunities')->nullOnDelete();
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->foreign('quotation_id', 'opp_quotation_fk')
                ->references('id')->on('quotations')->nullOnDelete();
            $table->foreign('sales_order_id', 'opp_sales_order_fk')
                ->references('id')->on('sales_orders')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // expenses — optional links to bill, project, recurring pattern
        // ----------------------------------------------------------------
        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreign('bill_id', 'expense_bill_fk')
                ->references('id')->on('bills')->nullOnDelete();
            $table->foreign('project_id', 'expense_project_fk')
                ->references('id')->on('projects')->nullOnDelete();
            $table->foreign('recurring_expense_id', 'expense_recurring_fk')
                ->references('id')->on('recurring_expenses')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // calendar_events — self-referential: recurring parent
        // ----------------------------------------------------------------
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->foreign('recurring_event_id', 'cal_event_recurring_parent_fk')
                ->references('id')->on('calendar_events')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // loan_payments / leave_encashments — optional payroll period link
        // ----------------------------------------------------------------
        Schema::table('loan_payments', function (Blueprint $table): void {
            $table->foreign('payroll_id', 'loan_payment_payroll_period_fk')
                ->references('id')->on('payroll_periods')->nullOnDelete();
        });

        Schema::table('leave_encashments', function (Blueprint $table): void {
            $table->foreign('payroll_id', 'leave_encash_payroll_period_fk')
                ->references('id')->on('payroll_periods')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // ecommerce_orders — link to the fulfilling sales order
        // ----------------------------------------------------------------
        Schema::table('ecommerce_orders', function (Blueprint $table): void {
            $table->foreign('sales_order_id', 'ecomm_order_sales_order_fk')
                ->references('id')->on('sales_orders')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // online_payments — link to the matching payment received record
        // ----------------------------------------------------------------
        Schema::table('online_payments', function (Blueprint $table): void {
            $table->foreign('payment_received_id', 'online_pay_payment_received_fk')
                ->references('id')->on('payments_received')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // bulk_sale_items — optional link to payment
        // ----------------------------------------------------------------
        Schema::table('bulk_sale_items', function (Blueprint $table): void {
            $table->foreign('payment_id', 'bulk_sale_item_payment_fk')
                ->references('id')->on('payments_received')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // advance_payments — optional wallet transaction link
        // credit_notes — optional bill and wallet transaction links
        // ----------------------------------------------------------------
        Schema::table('advance_payments', function (Blueprint $table): void {
            $table->foreign('wallet_transaction_id', 'adv_pay_wallet_txn_fk')
                ->references('id')->on('wallet_transactions')->nullOnDelete();
        });

        Schema::table('credit_notes', function (Blueprint $table): void {
            $table->foreign('bill_id', 'credit_note_bill_fk')
                ->references('id')->on('bills')->nullOnDelete();
            $table->foreign('wallet_transaction_id', 'credit_note_wallet_txn_fk')
                ->references('id')->on('wallet_transactions')->nullOnDelete();
        });

        Schema::table('debit_notes', function (Blueprint $table): void {
            $table->foreign('bill_id', 'debit_note_bill_fk')
                ->references('id')->on('bills')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // credit_note_items — link back to original invoice line and unit
        // ----------------------------------------------------------------
        Schema::table('credit_note_items', function (Blueprint $table): void {
            $table->foreign('original_invoice_line_id', 'cn_item_orig_inv_line_fk')
                ->references('id')->on('invoice_lines')->nullOnDelete();
            $table->foreign('unit_id', 'cn_item_unit_fk')
                ->references('id')->on('units_of_measure')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // refunds — optional links to sales return and payment received
        // ----------------------------------------------------------------
        Schema::table('refunds', function (Blueprint $table): void {
            $table->foreign('sales_return_id', 'refund_sales_return_fk')
                ->references('id')->on('sales_returns')->nullOnDelete();
            $table->foreign('payment_received_id', 'refund_payment_received_fk')
                ->references('id')->on('payments_received')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // sales_returns — optional credit note, refund, exchange links
        // ----------------------------------------------------------------
        Schema::table('sales_returns', function (Blueprint $table): void {
            $table->foreign('credit_note_id', 'sales_ret_credit_note_fk')
                ->references('id')->on('credit_notes')->nullOnDelete();
            $table->foreign('refund_id', 'sales_ret_refund_fk')
                ->references('id')->on('refunds')->nullOnDelete();
            $table->foreign('exchange_order_id', 'sales_ret_exchange_order_fk')
                ->references('id')->on('exchange_orders')->nullOnDelete();
        });

        Schema::table('sales_return_items', function (Blueprint $table): void {
            $table->foreign('invoice_item_id', 'sales_ret_item_inv_line_fk')
                ->references('id')->on('invoice_lines')->nullOnDelete();
            $table->foreign('warehouse_location_id', 'sales_ret_item_wh_location_fk')
                ->references('id')->on('warehouse_locations')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // purchase_returns — optional debit note and replacement PO links
        // ----------------------------------------------------------------
        Schema::table('purchase_returns', function (Blueprint $table): void {
            $table->foreign('debit_note_id', 'pur_ret_debit_note_fk')
                ->references('id')->on('debit_notes')->nullOnDelete();
            $table->foreign('replacement_po_id', 'pur_ret_replacement_po_fk')
                ->references('id')->on('purchase_orders')->nullOnDelete();
        });

        Schema::table('purchase_return_items', function (Blueprint $table): void {
            $table->foreign('bill_item_id', 'pur_ret_item_bill_line_fk')
                ->references('id')->on('bill_lines')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // import_export_shipments — optional landed cost voucher link
        // ----------------------------------------------------------------
        Schema::table('import_export_shipments', function (Blueprint $table): void {
            $table->foreign('landed_cost_voucher_id', 'shipment_landed_cost_fk')
                ->references('id')->on('landed_cost_vouchers')->nullOnDelete();
        });

        // ----------------------------------------------------------------
        // fraud_alerts — link to a contact (customer/supplier)
        // ----------------------------------------------------------------
        Schema::table('fraud_alerts', function (Blueprint $table): void {
            $table->foreign('contact_id', 'fraud_alert_contact_fk')
                ->references('id')->on('contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $constraints = [
            'journal_entry_lines'      => ['jel_contact_fk', 'jel_cost_center_fk'],
            'invoices'                 => ['inv_quotation_fk', 'inv_sales_order_fk'],
            'work_orders'              => ['wo_sales_order_fk', 'wo_sales_order_line_fk'],
            'production_logs'          => ['prod_log_stock_movement_fk'],
            'material_transactions'    => ['mat_txn_stock_movement_fk'],
            'scheduled_emails'         => ['sched_email_log_fk'],
            'bank_transactions'        => ['bank_txn_matched_pair_fk'],
            'leads'                    => ['lead_converted_opp_fk'],
            'opportunities'            => ['opp_quotation_fk', 'opp_sales_order_fk'],
            'expenses'                 => ['expense_bill_fk', 'expense_project_fk', 'expense_recurring_fk'],
            'calendar_events'          => ['cal_event_recurring_parent_fk'],
            'loan_payments'            => ['loan_payment_payroll_period_fk'],
            'leave_encashments'        => ['leave_encash_payroll_period_fk'],
            'ecommerce_orders'         => ['ecomm_order_sales_order_fk'],
            'online_payments'          => ['online_pay_payment_received_fk'],
            'bulk_sale_items'          => ['bulk_sale_item_payment_fk'],
            'advance_payments'         => ['adv_pay_wallet_txn_fk'],
            'credit_notes'             => ['credit_note_bill_fk', 'credit_note_wallet_txn_fk'],
            'debit_notes'              => ['debit_note_bill_fk'],
            'credit_note_items'        => ['cn_item_orig_inv_line_fk', 'cn_item_unit_fk'],
            'refunds'                  => ['refund_sales_return_fk', 'refund_payment_received_fk'],
            'sales_returns'            => ['sales_ret_credit_note_fk', 'sales_ret_refund_fk', 'sales_ret_exchange_order_fk'],
            'sales_return_items'       => ['sales_ret_item_inv_line_fk', 'sales_ret_item_wh_location_fk'],
            'purchase_returns'         => ['pur_ret_debit_note_fk', 'pur_ret_replacement_po_fk'],
            'purchase_return_items'    => ['pur_ret_item_bill_line_fk'],
            'import_export_shipments'  => ['shipment_landed_cost_fk'],
            'fraud_alerts'             => ['fraud_alert_contact_fk'],
        ];

        foreach ($constraints as $table => $fks) {
            Schema::table($table, function (Blueprint $table) use ($fks): void {
                foreach ($fks as $fk) {
                    $table->dropForeign($fk);
                }
            });
        }
    }
};
