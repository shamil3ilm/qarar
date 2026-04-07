<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intercompany_sales_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('selling_organization_id');
            $table->foreign('selling_organization_id', 'icso_selling_org_fk')
                ->references('id')->on('organizations')->restrictOnDelete();

            $table->unsignedBigInteger('buying_organization_id');
            $table->foreign('buying_organization_id', 'icso_buying_org_fk')
                ->references('id')->on('organizations')->restrictOnDelete();

            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->foreign('sales_order_id', 'icso_sales_order_fk')
                ->references('id')->on('sales_orders')->nullOnDelete();

            $table->string('order_number', 50);
            $table->enum('status', ['draft', 'confirmed', 'in_delivery', 'billed', 'cancelled'])->default('draft');
            $table->date('order_date');
            $table->date('requested_delivery_date')->nullable();

            $table->unsignedBigInteger('transfer_price_version_id')->nullable();
            $table->foreign('transfer_price_version_id', 'icso_tpv_fk')
                ->references('id')->on('transfer_price_versions')->nullOnDelete();

            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by', 'icso_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['selling_organization_id', 'status'], 'icso_selling_org_status_idx');
            $table->index(['buying_organization_id'], 'icso_buying_org_idx');
            $table->unique(['selling_organization_id', 'order_number'], 'icso_org_order_number_unq');
        });

        Schema::create('intercompany_sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('intercompany_sales_order_id');
            $table->foreign('intercompany_sales_order_id', 'icsol_order_fk')
                ->references('id')->on('intercompany_sales_orders')->cascadeOnDelete();

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'icsol_product_fk')
                ->references('id')->on('products')->restrictOnDelete();

            $table->unsignedSmallInteger('line_number');
            $table->string('description')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->string('unit_of_measure', 20)->nullable();
            $table->decimal('transfer_price', 18, 4);
            $table->decimal('list_price', 18, 4)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            $table->decimal('delivered_quantity', 18, 4)->default(0);
            $table->decimal('billed_quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->index(['intercompany_sales_order_id'], 'icsol_order_idx');
        });

        Schema::create('ic_purchase_order_links', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('intercompany_sales_order_id');
            $table->foreign('intercompany_sales_order_id', 'icpol_icso_fk')
                ->references('id')->on('intercompany_sales_orders')->cascadeOnDelete();

            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->foreign('purchase_order_id', 'icpol_po_fk')
                ->references('id')->on('purchase_orders')->nullOnDelete();

            $table->unsignedBigInteger('buying_organization_id');
            $table->foreign('buying_organization_id', 'icpol_buying_org_fk')
                ->references('id')->on('organizations')->restrictOnDelete();

            $table->enum('status', ['pending', 'linked', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->unique(['intercompany_sales_order_id'], 'icpol_icso_unq');
        });

        Schema::create('ic_billing_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('intercompany_sales_order_id');
            $table->foreign('intercompany_sales_order_id', 'icbd_icso_fk')
                ->references('id')->on('intercompany_sales_orders')->restrictOnDelete();

            $table->unsignedBigInteger('selling_organization_id');
            $table->foreign('selling_organization_id', 'icbd_selling_org_fk')
                ->references('id')->on('organizations')->restrictOnDelete();

            $table->unsignedBigInteger('buying_organization_id');
            $table->foreign('buying_organization_id', 'icbd_buying_org_fk')
                ->references('id')->on('organizations')->restrictOnDelete();

            $table->string('document_number', 50);
            $table->date('billing_date');
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('subtotal', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');

            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id', 'icbd_je_fk')
                ->references('id')->on('journal_entries')->nullOnDelete();

            $table->dateTime('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['selling_organization_id', 'document_number'], 'icbd_org_doc_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ic_billing_documents');
        Schema::dropIfExists('ic_purchase_order_links');
        Schema::dropIfExists('intercompany_sales_order_lines');
        Schema::dropIfExists('intercompany_sales_orders');
    }
};
