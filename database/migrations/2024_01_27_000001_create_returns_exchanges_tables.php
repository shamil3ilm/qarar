<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Return reasons (configurable per organization)
        Schema::create('return_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        // Sales returns (customer returns)
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('return_number', 30);
            $table->foreignId('customer_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();

            // Return details
            $table->date('return_date');
            $table->foreignId('return_reason_id')->nullable()->constrained('return_reasons')->nullOnDelete();
            $table->text('reason_notes')->nullable();
            $table->string('return_type', 20)->default('refund'); // refund, exchange, credit_note, replacement

            // Amounts
            $table->string('currency_code', 3);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('restocking_fee', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('refund_amount', 15, 2)->default(0);

            // Status workflow
            $table->string('status', 30)->default('pending'); // pending, approved, received, inspected, completed, rejected, cancelled
            $table->string('inspection_status', 20)->nullable(); // pending, passed, failed, partial
            $table->text('inspection_notes')->nullable();

            // Resolution
            $table->string('resolution_type', 30)->nullable(); // full_refund, partial_refund, exchange, credit_note, replacement, rejected
            $table->foreignId('credit_note_id')->nullable(); // Link to generated credit note
            $table->foreignId('refund_id')->nullable(); // Link to refund record
            $table->foreignId('exchange_order_id')->nullable(); // Link to replacement sales order

            // Inventory
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->boolean('restock_items')->default(true);
            $table->boolean('items_received')->default(false);
            $table->timestamp('received_at')->nullable();

            // Approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Accounting
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'return_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['invoice_id']);
            $table->index(['organization_id', 'return_date']);
        });

        // Sales return items
        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable(); // Link to original invoice item
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->string('description')->nullable();

            $table->decimal('quantity_returned', 15, 4);
            $table->decimal('quantity_received', 15, 4)->default(0);
            $table->decimal('quantity_restocked', 15, 4)->default(0);
            $table->decimal('quantity_damaged', 15, 4)->default(0);

            $table->decimal('unit_price', 15, 4);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total', 15, 2);

            $table->string('condition', 30)->nullable(); // new, like_new, used, damaged, defective
            $table->text('condition_notes')->nullable();

            $table->string('item_status', 20)->default('pending'); // pending, received, inspected, restocked, disposed
            $table->foreignId('warehouse_location_id')->nullable();

            $table->timestamps();

            $table->index(['sales_return_id']);
            $table->index(['product_id']);
        });

        // Purchase returns (returns to suppliers)
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('return_number', 30);
            $table->foreignId('supplier_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();

            $table->date('return_date');
            $table->foreignId('return_reason_id')->nullable()->constrained('return_reasons')->nullOnDelete();
            $table->text('reason_notes')->nullable();
            $table->string('return_type', 20)->default('debit_note'); // debit_note, replacement, refund

            // Amounts
            $table->string('currency_code', 3);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            // Status
            $table->string('status', 30)->default('draft'); // draft, approved, shipped, received_by_supplier, completed, cancelled
            $table->string('resolution_type', 30)->nullable(); // debit_note, replacement, refund
            $table->foreignId('debit_note_id')->nullable();
            $table->foreignId('replacement_po_id')->nullable();

            // Shipping
            $table->string('shipping_method')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('supplier_received_at')->nullable();

            // Accounting
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            // Approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'return_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index(['bill_id']);
        });

        // Purchase return items
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('bill_item_id')->nullable();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->string('description')->nullable();

            $table->decimal('quantity_returned', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total', 15, 2);

            $table->string('condition', 30)->nullable(); // defective, damaged, wrong_item, quality_issue
            $table->text('condition_notes')->nullable();
            $table->string('item_status', 20)->default('pending');

            $table->timestamps();

            $table->index(['purchase_return_id']);
            $table->index(['product_id']);
        });

        // Return merchandise authorization (RMA)
        Schema::create('rma_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('rma_number', 30);
            $table->string('rma_type', 20); // sales_return, purchase_return
            $table->foreignId('customer_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();

            $table->text('description');
            $table->string('requested_resolution', 30); // refund, exchange, repair, credit
            $table->string('status', 30)->default('pending'); // pending, approved, in_progress, completed, rejected, expired
            $table->date('request_date');
            $table->date('expiry_date')->nullable(); // RMA validity period

            $table->foreignId('sales_return_id')->nullable()->constrained('sales_returns')->nullOnDelete();
            $table->foreignId('purchase_return_id')->nullable()->constrained('purchase_returns')->nullOnDelete();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'rma_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['rma_type', 'status']);
        });

        // RMA items
        Schema::create('rma_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rma_request_id')->constrained('rma_requests')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->string('reason', 50); // defective, wrong_item, not_as_described, damaged_in_transit, quality_issue
            $table->text('description')->nullable();
            $table->json('evidence_paths')->nullable(); // Photos/docs
            $table->timestamps();

            $table->index(['rma_request_id']);
        });

        // Exchange orders (links returned items to replacement)
        Schema::create('exchange_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('exchange_number', 30);
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('contacts')->cascadeOnDelete();

            // Price difference
            $table->decimal('original_total', 15, 2);
            $table->decimal('exchange_total', 15, 2);
            $table->decimal('price_difference', 15, 2)->default(0); // Positive = customer pays, negative = refund
            $table->string('difference_resolution', 30)->nullable(); // payment, credit_note, waived

            // Linked documents
            $table->foreignId('new_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('new_sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();

            $table->string('status', 30)->default('pending'); // pending, processing, shipped, completed, cancelled
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'exchange_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['customer_id']);
        });

        // Exchange order items (what the customer gets as replacement)
        Schema::create('exchange_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_order_id')->constrained('exchange_orders')->cascadeOnDelete();
            $table->foreignId('original_product_id')->constrained('products')->cascadeOnDelete(); // What was returned
            $table->foreignId('replacement_product_id')->constrained('products')->cascadeOnDelete(); // What they get
            $table->foreignId('replacement_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('original_quantity', 15, 4);
            $table->decimal('replacement_quantity', 15, 4);
            $table->decimal('original_unit_price', 15, 4);
            $table->decimal('replacement_unit_price', 15, 4);
            $table->decimal('price_difference', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['exchange_order_id']);
        });

        // Return policy configuration
        Schema::create('return_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('return_window_days')->default(30); // Days after purchase
            $table->boolean('allow_exchange')->default(true);
            $table->boolean('allow_refund')->default(true);
            $table->boolean('allow_credit_note')->default(true);
            $table->boolean('require_receipt')->default(true);
            $table->boolean('require_original_packaging')->default(false);
            $table->boolean('require_approval')->default(true);
            $table->decimal('restocking_fee_percent', 5, 2)->default(0);
            $table->json('non_returnable_categories')->nullable(); // Category IDs that can't be returned
            $table->json('condition_requirements')->nullable(); // Condition must be X to return
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_policies');
        Schema::dropIfExists('exchange_order_items');
        Schema::dropIfExists('exchange_orders');
        Schema::dropIfExists('rma_items');
        Schema::dropIfExists('rma_requests');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('return_reasons');
    }
};
