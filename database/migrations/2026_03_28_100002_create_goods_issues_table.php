<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_issues', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->string('gi_number')->unique();
            $table->date('gi_date');

            // Movement type: what triggered this goods issue
            $table->string('movement_type'); // sales_delivery, production_issue, scrapping, transfer, other

            // Polymorphic reference to the source document (Invoice, SalesOrder, WorkOrder, etc.)
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id']);

            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');

            $table->string('status')->default('draft'); // draft, posted, reversed

            $table->decimal('total_quantity', 10, 4)->default(0);
            $table->decimal('total_value', 15, 4)->default(0);

            $table->text('notes')->nullable();

            // Posting metadata
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('posted_at')->nullable();

            // Reversal metadata
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->foreign('reversed_by')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();

            // GL journal entry generated on posting
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'gi_date']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('goods_issue_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('goods_issue_id');
            $table->foreign('goods_issue_id')->references('id')->on('goods_issues')->cascadeOnDelete();

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products');

            $table->unsignedBigInteger('variant_id')->nullable();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();

            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');

            $table->unsignedBigInteger('location_id')->nullable();
            $table->foreign('location_id')->references('id')->on('warehouse_locations')->nullOnDelete();

            $table->unsignedBigInteger('batch_id')->nullable();
            $table->foreign('batch_id')->references('id')->on('inventory_batches')->nullOnDelete();

            $table->decimal('quantity', 10, 4);

            $table->unsignedBigInteger('unit_id')->nullable();
            $table->foreign('unit_id')->references('id')->on('units_of_measure')->nullOnDelete();

            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_value', 15, 4)->default(0);

            $table->string('serial_number')->nullable();
            $table->string('notes')->nullable();

            $table->timestamps();

            $table->index('goods_issue_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_issue_lines');
        Schema::dropIfExists('goods_issues');
    }
};
