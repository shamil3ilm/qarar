<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serial_numbers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('serial_number', 100);
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->enum('status', ['in_stock', 'sold', 'returned', 'scrapped', 'in_transit'])->default('in_stock');
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('warranty_expiry_date')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->foreignId('sold_to_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('current_document_type', 50)->nullable();
            $table->unsignedBigInteger('current_document_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'product_id', 'serial_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'warehouse_id']);
        });

        Schema::create('serial_number_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('serial_number_id')->constrained('serial_numbers')->cascadeOnDelete();
            $table->enum('movement_type', ['receipt', 'issue', 'transfer', 'return', 'scrap']);
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('document_type', 50)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moved_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'serial_number_id']);
            $table->index(['organization_id', 'movement_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_number_movements');
        Schema::dropIfExists('serial_numbers');
    }
};
