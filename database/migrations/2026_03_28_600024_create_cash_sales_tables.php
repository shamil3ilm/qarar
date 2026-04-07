<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cash_sale_lines');
        Schema::dropIfExists('cash_sales');

        Schema::create('cash_sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('cash_sale_number')->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreign('customer_id', 'cs_cust_fk')->references('id')->on('contacts')->onDelete('set null');
            $table->unsignedBigInteger('cashier_id');
            $table->foreign('cashier_id', 'cs_cashier_fk')->references('id')->on('users')->onDelete('restrict');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreign('branch_id', 'cs_branch_fk')->references('id')->on('branches')->onDelete('set null');
            $table->timestamp('sale_date');
            $table->decimal('subtotal', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->enum('payment_method', ['cash', 'card', 'wallet', 'mixed']);
            $table->decimal('amount_tendered', 18, 4)->nullable();
            $table->decimal('change_given', 18, 4)->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->foreign('invoice_id', 'cs_inv_fk')->references('id')->on('invoices')->onDelete('set null');
            $table->enum('status', ['open', 'completed', 'voided'])->default('open');
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->foreign('voided_by', 'cs_void_usr_fk')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cash_sale_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cash_sale_id');
            $table->foreign('cash_sale_id', 'cs_line_fk')->references('id')->on('cash_sales')->onDelete('cascade');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'cs_line_prod_fk')->references('id')->on('products')->onDelete('restrict');
            $table->decimal('quantity', 18, 4);
            $table->string('uom', 20);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount_pct', 8, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sale_lines');
        Schema::dropIfExists('cash_sales');
    }
};
