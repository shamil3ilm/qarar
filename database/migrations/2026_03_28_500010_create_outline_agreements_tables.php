<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outline_agreements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id', 'oa_vendor_fk')->references('id')->on('contacts');
            $table->string('agreement_number', 50);
            $table->enum('agreement_type', ['quantity_contract', 'value_contract', 'scheduling_agreement'])->default('quantity_contract');
            $table->enum('status', ['draft', 'active', 'expired', 'cancelled'])->default('draft');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('target_quantity', 18, 4)->nullable();
            $table->decimal('target_value', 18, 4)->nullable();
            $table->decimal('released_quantity', 18, 4)->default(0);
            $table->decimal('released_value', 18, 4)->default(0);
            $table->string('payment_terms', 100)->nullable();
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by', 'oa_created_by_fk')->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'agreement_number'], 'oa_org_number_unq');
            $table->index(['organization_id', 'vendor_id', 'status'], 'oa_org_vendor_status_idx');
        });

        Schema::create('outline_agreement_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'oai_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('outline_agreement_id');
            $table->foreign('outline_agreement_id', 'oai_agreement_fk')->references('id')->on('outline_agreements');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'oai_product_fk')->references('id')->on('products');
            $table->unsignedSmallInteger('line_number');
            $table->string('description')->nullable();
            $table->decimal('target_quantity', 18, 4)->nullable();
            $table->decimal('target_value', 18, 4)->nullable();
            $table->decimal('released_quantity', 18, 4)->default(0);
            $table->decimal('released_value', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->string('unit_of_measure', 20)->nullable();
            $table->timestamps();

            $table->index(['outline_agreement_id'], 'oai_agreement_idx');
        });

        Schema::create('outline_agreement_releases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'oar_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('outline_agreement_id');
            $table->foreign('outline_agreement_id', 'oar_agreement_fk')->references('id')->on('outline_agreements');
            $table->unsignedBigInteger('outline_agreement_item_id')->nullable();
            $table->foreign('outline_agreement_item_id', 'oar_item_fk')->references('id')->on('outline_agreement_items');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->foreign('purchase_order_id', 'oar_po_fk')->references('id')->on('purchase_orders');
            $table->date('release_date');
            $table->decimal('release_quantity', 18, 4)->nullable();
            $table->decimal('release_value', 18, 4)->nullable();
            $table->enum('status', ['open', 'goods_received', 'invoiced', 'cancelled'])->default('open');
            $table->timestamps();

            $table->index(['outline_agreement_id'], 'oar_agreement_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outline_agreement_releases');
        Schema::dropIfExists('outline_agreement_items');
        Schema::dropIfExists('outline_agreements');
    }
};
