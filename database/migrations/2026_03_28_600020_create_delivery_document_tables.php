<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pick_document_lines');
        Schema::dropIfExists('pick_documents');
        Schema::dropIfExists('delivery_document_lines');
        Schema::dropIfExists('delivery_documents');

        Schema::create('delivery_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('delivery_number')->unique();
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->foreign('sales_order_id', 'del_doc_so_fk')->references('id')->on('sales_orders')->onDelete('set null');
            $table->unsignedBigInteger('ship_to_contact_id')->nullable();
            $table->foreign('ship_to_contact_id', 'del_doc_ship_fk')->references('id')->on('contacts')->onDelete('set null');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id', 'del_doc_wh_fk')->references('id')->on('warehouses')->onDelete('set null');
            $table->date('planned_goods_issue_date');
            $table->date('actual_goods_issue_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->enum('status', ['created', 'picking', 'picked', 'packed', 'goods_issued', 'cancelled'])->default('created');
            $table->decimal('weight_gross', 10, 3)->nullable();
            $table->decimal('weight_net', 10, 3)->nullable();
            $table->decimal('volume', 10, 3)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('delivery_document_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('delivery_document_id');
            $table->foreign('delivery_document_id', 'del_doc_line_fk')->references('id')->on('delivery_documents')->onDelete('cascade');
            $table->unsignedBigInteger('sales_order_line_id')->nullable();
            $table->foreign('sales_order_line_id', 'del_doc_line_sol_fk')->references('id')->on('sales_order_lines')->onDelete('set null');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'del_doc_line_prod_fk')->references('id')->on('products')->onDelete('restrict');
            $table->decimal('delivery_quantity', 18, 4);
            $table->decimal('picked_quantity', 18, 4)->default(0);
            $table->decimal('packed_quantity', 18, 4)->default(0);
            $table->decimal('issued_quantity', 18, 4)->default(0);
            $table->string('uom', 20);
            $table->string('batch_number')->nullable();
            $table->unsignedBigInteger('warehouse_location_id')->nullable();
            $table->foreign('warehouse_location_id', 'del_doc_line_wloc_fk')->references('id')->on('warehouse_locations')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('pick_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('pick_number')->unique();
            $table->unsignedBigInteger('delivery_document_id');
            $table->foreign('delivery_document_id', 'pick_doc_del_fk')->references('id')->on('delivery_documents')->onDelete('cascade');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->foreign('assigned_to', 'pick_doc_usr_fk')->references('id')->on('users')->onDelete('set null');
            $table->enum('status', ['open', 'in_progress', 'completed', 'cancelled'])->default('open');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pick_document_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pick_document_id');
            $table->foreign('pick_document_id', 'pick_doc_line_fk')->references('id')->on('pick_documents')->onDelete('cascade');
            $table->unsignedBigInteger('delivery_document_line_id');
            $table->foreign('delivery_document_line_id', 'pick_doc_line_dl_fk')->references('id')->on('delivery_document_lines')->onDelete('cascade');
            $table->decimal('required_quantity', 18, 4);
            $table->decimal('picked_quantity', 18, 4)->default(0);
            $table->string('storage_bin')->nullable();
            $table->enum('status', ['open', 'partial', 'completed'])->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_document_lines');
        Schema::dropIfExists('pick_documents');
        Schema::dropIfExists('delivery_document_lines');
        Schema::dropIfExists('delivery_documents');
    }
};
