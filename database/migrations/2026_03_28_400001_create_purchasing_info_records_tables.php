<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('purchasing_info_record_conditions');
        Schema::dropIfExists('purchasing_info_records');

        Schema::create('purchasing_info_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'pir_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->foreign('vendor_id', 'pir_vendor_fk')
                ->references('id')->on('contacts')->onDelete('set null');

            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'pir_product_fk')
                ->references('id')->on('products')->onDelete('set null');

            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id', 'pir_warehouse_fk')
                ->references('id')->on('warehouses')->onDelete('set null');

            $table->enum('info_category', ['standard', 'subcontracting', 'consignment', 'pipeline'])
                ->default('standard');
            $table->boolean('is_active')->default(true);

            $table->unsignedSmallInteger('planned_delivery_days')->nullable();
            $table->unsignedSmallInteger('reminder_days')->nullable()
                ->comment('Days before delivery to send reminder');

            $table->decimal('overdelivery_tolerance', 5, 2)->nullable()
                ->comment('Over-delivery tolerance percentage');
            $table->decimal('underdelivery_tolerance', 5, 2)->nullable()
                ->comment('Under-delivery tolerance percentage');
            $table->boolean('is_underdelivery_tolerated')->default(false);

            $table->decimal('net_price', 18, 4)->nullable();
            $table->unsignedInteger('price_unit')->default(1);
            $table->char('currency_code', 3)->default('SAR');

            $table->decimal('minimum_order_quantity', 18, 4)->nullable();
            $table->decimal('standard_order_quantity', 18, 4)->nullable();

            $table->date('last_purchase_date')->nullable();
            $table->decimal('last_purchase_price', 18, 4)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'vendor_id', 'product_id', 'info_category'],
                'pir_org_vendor_product_category_unq'
            );
            $table->index(['organization_id', 'product_id'], 'pir_org_product_idx');
        });

        Schema::create('purchasing_info_record_conditions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'pir_cond_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unsignedBigInteger('purchasing_info_record_id');
            $table->foreign('purchasing_info_record_id', 'pir_cond_pir_fk')
                ->references('id')->on('purchasing_info_records')->onDelete('cascade');

            $table->date('valid_from');
            $table->date('valid_to')->nullable();

            $table->decimal('net_price', 18, 4);
            $table->unsignedInteger('price_unit')->default(1);
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(
                ['purchasing_info_record_id', 'valid_from'],
                'pir_cond_pir_valid_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchasing_info_record_conditions');
        Schema::dropIfExists('purchasing_info_records');
    }
};
