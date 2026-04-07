<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dim_organization', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'dim_org_org_id_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->string('org_name');
            $table->string('org_type', 50)->nullable();
            $table->char('country_code', 3);
            $table->char('currency_code', 3);
            $table->tinyInteger('fiscal_year_start_month')->default(1);
            $table->timestamps();
        });

        Schema::create('dim_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'dim_product_prod_id_fk')
                ->references('id')->on('products')->onDelete('set null');
            $table->string('product_code', 50);
            $table->string('product_name');
            $table->string('category_name', 100);
            $table->string('subcategory_name', 100)->nullable();
            $table->string('unit_of_measure', 20);
            $table->string('product_type', 30);
            $table->boolean('is_active')->default(true);
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->index('organization_id', 'dim_product_org_id_idx');
        });

        Schema::create('dim_customer', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->foreign('contact_id', 'dim_customer_contact_id_fk')
                ->references('id')->on('contacts')->onDelete('set null');
            $table->string('customer_code', 50);
            $table->string('customer_name');
            $table->string('customer_group', 50)->nullable();
            $table->char('country_code', 3)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('currency_code', 3);
            $table->decimal('credit_limit', 18, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->index('organization_id', 'dim_customer_org_id_idx');
        });

        Schema::create('dim_vendor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->foreign('contact_id', 'dim_vendor_contact_id_fk')
                ->references('id')->on('contacts')->onDelete('set null');
            $table->string('vendor_code', 50);
            $table->string('vendor_name');
            $table->string('vendor_group', 50)->nullable();
            $table->char('country_code', 3)->nullable();
            $table->char('currency_code', 3);
            $table->string('payment_terms', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->index('organization_id', 'dim_vendor_org_id_idx');
        });

        Schema::create('dim_warehouse', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id', 'dim_warehouse_wh_id_fk')
                ->references('id')->on('warehouses')->onDelete('set null');
            $table->string('warehouse_code', 30);
            $table->string('warehouse_name');
            $table->string('location', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('organization_id', 'dim_warehouse_org_id_idx');
        });

        Schema::create('dim_time', function (Blueprint $table) {
            $table->id();
            $table->date('full_date')->unique();
            $table->tinyInteger('day_of_week');
            $table->string('day_name', 10);
            $table->tinyInteger('day_of_month');
            $table->tinyInteger('week_of_year');
            $table->tinyInteger('month_number');
            $table->string('month_name', 10);
            $table->tinyInteger('quarter');
            $table->smallInteger('year');
            $table->smallInteger('fiscal_year');
            $table->tinyInteger('fiscal_period');
            $table->boolean('is_weekend')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->timestamps();

            $table->index(['year', 'month_number'], 'dim_time_year_month_idx');
            $table->index(['fiscal_year', 'fiscal_period'], 'dim_time_fiscal_idx');
        });

        Schema::create('fact_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('dim_product_id');
            $table->foreign('dim_product_id', 'fact_sales_prod_id_fk')
                ->references('id')->on('dim_product')->onDelete('restrict');
            $table->unsignedBigInteger('dim_customer_id');
            $table->foreign('dim_customer_id', 'fact_sales_cust_id_fk')
                ->references('id')->on('dim_customer')->onDelete('restrict');
            $table->unsignedBigInteger('dim_time_id');
            $table->foreign('dim_time_id', 'fact_sales_time_id_fk')
                ->references('id')->on('dim_time')->onDelete('restrict');
            $table->unsignedBigInteger('dim_warehouse_id')->nullable();
            $table->foreign('dim_warehouse_id', 'fact_sales_wh_id_fk')
                ->references('id')->on('dim_warehouse')->onDelete('set null');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('invoice_line_id')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('net_amount', 18, 4);
            $table->decimal('tax_amount', 18, 4);
            $table->decimal('gross_amount', 18, 4);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('cost_amount', 18, 4)->default(0);
            $table->decimal('gross_margin', 18, 4)->default(0);
            $table->char('currency_code', 3);
            $table->timestamps();

            $table->index(['organization_id', 'dim_time_id'], 'fact_sales_org_time_idx');
            $table->index(['dim_customer_id', 'dim_time_id'], 'fact_sales_cust_time_idx');
            $table->index(['dim_product_id', 'dim_time_id'], 'fact_sales_prod_time_idx');
        });

        Schema::create('fact_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('dim_product_id');
            $table->foreign('dim_product_id', 'fact_purch_prod_id_fk')
                ->references('id')->on('dim_product')->onDelete('restrict');
            $table->unsignedBigInteger('dim_vendor_id');
            $table->foreign('dim_vendor_id', 'fact_purch_vendor_id_fk')
                ->references('id')->on('dim_vendor')->onDelete('restrict');
            $table->unsignedBigInteger('dim_time_id');
            $table->foreign('dim_time_id', 'fact_purch_time_id_fk')
                ->references('id')->on('dim_time')->onDelete('restrict');
            $table->unsignedBigInteger('dim_warehouse_id')->nullable();
            $table->foreign('dim_warehouse_id', 'fact_purch_wh_id_fk')
                ->references('id')->on('dim_warehouse')->onDelete('set null');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('net_amount', 18, 4);
            $table->decimal('tax_amount', 18, 4);
            $table->decimal('gross_amount', 18, 4);
            $table->char('currency_code', 3);
            $table->timestamps();

            $table->index(['organization_id', 'dim_time_id'], 'fact_purch_org_time_idx');
            $table->index(['dim_vendor_id', 'dim_time_id'], 'fact_purch_vendor_time_idx');
            $table->index(['dim_product_id', 'dim_time_id'], 'fact_purch_prod_time_idx');
        });

        Schema::create('fact_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('dim_product_id');
            $table->foreign('dim_product_id', 'fact_inv_prod_id_fk')
                ->references('id')->on('dim_product')->onDelete('restrict');
            $table->unsignedBigInteger('dim_warehouse_id');
            $table->foreign('dim_warehouse_id', 'fact_inv_wh_id_fk')
                ->references('id')->on('dim_warehouse')->onDelete('restrict');
            $table->unsignedBigInteger('dim_time_id');
            $table->foreign('dim_time_id', 'fact_inv_time_id_fk')
                ->references('id')->on('dim_time')->onDelete('restrict');
            $table->string('movement_type', 30);
            $table->decimal('quantity_in', 18, 4)->default(0);
            $table->decimal('quantity_out', 18, 4)->default(0);
            $table->decimal('quantity_balance', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->char('currency_code', 3);
            $table->string('reference_type', 50)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'dim_time_id'], 'fact_inv_org_time_idx');
            $table->index(['dim_product_id', 'dim_warehouse_id', 'dim_time_id'], 'fact_inv_prod_wh_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_inventory_movements');
        Schema::dropIfExists('fact_purchases');
        Schema::dropIfExists('fact_sales');
        Schema::dropIfExists('dim_time');
        Schema::dropIfExists('dim_warehouse');
        Schema::dropIfExists('dim_vendor');
        Schema::dropIfExists('dim_customer');
        Schema::dropIfExists('dim_product');
        Schema::dropIfExists('dim_organization');
    }
};
