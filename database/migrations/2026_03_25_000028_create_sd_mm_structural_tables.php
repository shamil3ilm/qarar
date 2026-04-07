<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SAP SD — Pricing Procedures (condition technique)
        Schema::create('pricing_procedures', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code'], 'pp_org_code_unique');
        });

        Schema::create('pricing_condition_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 10); // PR00, K007, MWST, etc.
            $table->string('name', 100);
            $table->enum('condition_class', ['price', 'discount', 'surcharge', 'tax', 'freight'])->default('price');
            $table->enum('calculation_type', ['fixed', 'percentage', 'quantity', 'weight', 'volume'])->default('percentage');
            $table->boolean('is_mandatory')->default(false);
            $table->integer('step')->default(10);
            $table->integer('counter')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'pct_org_code_unique');
        });

        Schema::create('pricing_condition_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condition_type_id')->constrained('pricing_condition_types')->cascadeOnDelete();
            $table->enum('key_combination', ['customer_material', 'customer', 'material', 'price_list', 'all'])->default('material');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('price_list_id')->nullable();
            $table->decimal('rate', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->decimal('min_quantity', 15, 4)->nullable();
            $table->decimal('max_quantity', 15, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['condition_type_id', 'key_combination'], 'pcr_type_key_idx');
            $table->index(['product_id', 'valid_from', 'valid_to'], 'pcr_product_dates_idx');
        });

        // SAP MM — Purchase Requisitions
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('requisition_number', 30);
            $table->date('requisition_date');
            $table->date('required_by_date')->nullable();
            $table->enum('requisition_type', ['standard', 'subcontracting', 'consignment', 'stock_transfer'])->default('standard');
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'converted_to_po', 'cancelled'])->default('draft');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'requisition_number'], 'pr_org_num_unique');
            $table->index(['organization_id', 'status'], 'pr_org_status_idx');
        });

        Schema::create('purchase_requisition_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('purchase_requisitions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('estimated_unit_price', 15, 4)->nullable();
            $table->foreignId('preferred_vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->date('required_by_date')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'partially_converted', 'converted', 'cancelled'])->default('open');
            $table->timestamps();
            $table->index(['requisition_id'], 'prl_req_idx');
            $table->index(['product_id', 'status'], 'prl_product_status_idx');
        });

        // SAP MM — Physical Inventory
        Schema::create('physical_inventory_documents', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('document_number', 30);
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->date('count_date');
            $table->enum('inventory_type', ['full', 'cycle', 'spot'])->default('full');
            $table->enum('status', ['created', 'in_progress', 'counted', 'posted', 'cancelled'])->default('created');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'document_number'], 'pid_org_doc_unique');
            $table->index(['organization_id', 'status'], 'pid_org_status_idx');
            $table->index(['warehouse_id', 'count_date'], 'pid_wh_date_idx');
        });

        Schema::create('physical_inventory_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('physical_inventory_documents')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->decimal('book_quantity', 15, 4);
            $table->decimal('counted_quantity', 15, 4)->nullable();
            $table->decimal('difference_quantity', 15, 4)->nullable();
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('difference_value', 15, 4)->nullable();
            $table->enum('adjustment_status', ['pending', 'adjusted', 'skipped'])->default('pending');
            $table->timestamps();
            $table->index(['document_id'], 'pil_doc_idx');
            $table->index(['product_id', 'adjustment_status'], 'pil_product_adj_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_inventory_lines');
        Schema::dropIfExists('physical_inventory_documents');
        Schema::dropIfExists('purchase_requisition_lines');
        Schema::dropIfExists('purchase_requisitions');
        Schema::dropIfExists('pricing_condition_records');
        Schema::dropIfExists('pricing_condition_types');
        Schema::dropIfExists('pricing_procedures');
    }
};
