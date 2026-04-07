<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('returns_inspection_defects');
        Schema::dropIfExists('returns_inspection_lots');

        Schema::create('returns_inspection_lots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unsignedBigInteger('rma_request_id')->nullable();
            $table->foreign('rma_request_id', 'ril_rma_fk')
                ->references('id')->on('rma_requests')->nullOnDelete();

            $table->unsignedBigInteger('sales_return_id')->nullable();
            $table->foreign('sales_return_id', 'ril_sales_return_fk')
                ->references('id')->on('sales_returns')->nullOnDelete();

            $table->unsignedBigInteger('purchase_return_id')->nullable();
            $table->foreign('purchase_return_id', 'ril_purchase_return_fk')
                ->references('id')->on('purchase_returns')->nullOnDelete();

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'ril_product_fk')
                ->references('id')->on('products');

            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id', 'ril_warehouse_fk')
                ->references('id')->on('warehouses')->nullOnDelete();

            $table->string('lot_number', 50);

            $table->enum('return_type', ['customer_return', 'vendor_return', 'internal_return'])
                ->default('customer_return');

            $table->enum('status', ['open', 'in_inspection', 'usage_decision_made', 'closed', 'cancelled'])
                ->default('open');

            $table->decimal('received_quantity', 18, 4);
            $table->decimal('inspected_quantity', 18, 4)->default(0);
            $table->decimal('accepted_quantity', 18, 4)->default(0);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->decimal('rework_quantity', 18, 4)->default(0);

            $table->enum('usage_decision', ['accept', 'reject', 'rework', 'partial_accept'])->nullable();

            $table->unsignedBigInteger('usage_decision_by')->nullable();
            $table->foreign('usage_decision_by', 'ril_ud_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->dateTime('usage_decision_at')->nullable();
            $table->text('usage_decision_notes')->nullable();

            $table->date('inspection_start_date')->nullable();
            $table->date('inspection_end_date')->nullable();

            $table->unsignedBigInteger('quality_plan_id')->nullable();
            $table->foreign('quality_plan_id', 'ril_qp_fk')
                ->references('id')->on('quality_plans')->nullOnDelete();

            $table->boolean('stock_posted')->default(false);
            $table->dateTime('stock_posted_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by', 'ril_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'lot_number'], 'ril_org_lot_unq');
            $table->index(['organization_id', 'status'], 'ril_org_status_idx');
            $table->index(['product_id'], 'ril_product_idx');
        });

        Schema::create('returns_inspection_defects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'rid_org_fk')
                ->references('id')->on('organizations')->cascadeOnDelete();

            $table->unsignedBigInteger('returns_inspection_lot_id');
            $table->foreign('returns_inspection_lot_id', 'rid_lot_fk')
                ->references('id')->on('returns_inspection_lots')->cascadeOnDelete();

            $table->string('defect_code', 50);
            $table->text('defect_description')->nullable();

            $table->enum('severity', ['critical', 'major', 'minor', 'cosmetic'])->default('minor');

            $table->decimal('quantity_affected', 18, 4)->default(0);

            $table->enum('recommended_action', ['scrap', 'return_to_vendor', 'rework', 'repack', 'accept'])->nullable();
            $table->enum('actual_action_taken', ['scrapped', 'returned_to_vendor', 'reworked', 'repacked', 'accepted'])->nullable();

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->foreign('recorded_by', 'rid_recorded_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['returns_inspection_lot_id'], 'rid_lot_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns_inspection_defects');
        Schema::dropIfExists('returns_inspection_lots');
    }
};
