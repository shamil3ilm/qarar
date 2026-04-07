<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('quota_arrangement_items');
        Schema::dropIfExists('quota_arrangements');

        Schema::create('quota_arrangements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'qa_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'qa_product_fk')
                ->references('id')->on('products')->onDelete('cascade');

            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id', 'qa_warehouse_fk')
                ->references('id')->on('warehouses')->onDelete('set null');

            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['organization_id', 'product_id', 'valid_from'],
                'qa_org_product_valid_idx'
            );
        });

        Schema::create('quota_arrangement_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'qai_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unsignedBigInteger('quota_arrangement_id');
            $table->foreign('quota_arrangement_id', 'qai_arrangement_fk')
                ->references('id')->on('quota_arrangements')->onDelete('cascade');

            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id', 'qai_vendor_fk')
                ->references('id')->on('contacts')->onDelete('cascade');

            $table->unsignedBigInteger('purchasing_info_record_id')->nullable();
            $table->foreign('purchasing_info_record_id', 'qai_pir_fk')
                ->references('id')->on('purchasing_info_records')->onDelete('set null');

            $table->decimal('quota_percentage', 5, 2)
                ->comment('Must sum to 100 across all items in the arrangement');
            $table->decimal('min_lot_size', 18, 4)->nullable();
            $table->decimal('max_lot_size', 18, 4)->nullable();
            $table->decimal('allocated_quantity', 18, 4)->default(0)
                ->comment('Running total of quantity assigned via this quota item');
            $table->dateTime('last_assigned_at')->nullable();
            $table->boolean('is_blocked')->default(false);

            $table->timestamps();

            $table->index(['quota_arrangement_id'], 'qai_arrangement_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_arrangement_items');
        Schema::dropIfExists('quota_arrangements');
    }
};
