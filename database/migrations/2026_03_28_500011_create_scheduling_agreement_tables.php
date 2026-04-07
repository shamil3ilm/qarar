<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduling_agreements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id', 'sa_vendor_fk')->references('id')->on('contacts');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'sa_product_fk')->references('id')->on('products');
            $table->string('agreement_number', 50);
            $table->enum('status', ['draft', 'active', 'expired', 'cancelled'])->default('draft');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->decimal('target_quantity', 18, 4);
            $table->decimal('released_quantity', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 4);
            $table->char('currency_code', 3)->default('SAR');
            $table->string('unit_of_measure', 20)->nullable();
            $table->unsignedSmallInteger('delivery_days')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'agreement_number'], 'sched_ag_org_number_unq');
            $table->index(['organization_id', 'vendor_id', 'product_id'], 'sched_ag_org_vendor_prod_idx');
        });

        Schema::create('sa_delivery_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'sads_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('scheduling_agreement_id');
            $table->foreign('scheduling_agreement_id', 'sads_sa_fk')->references('id')->on('scheduling_agreements');
            $table->date('schedule_date');
            $table->decimal('scheduled_quantity', 18, 4);
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->enum('status', ['open', 'partial', 'complete', 'cancelled'])->default('open');
            $table->unsignedBigInteger('goods_receipt_id')->nullable();
            $table->foreign('goods_receipt_id', 'sads_gr_fk')->references('id')->on('goods_receipts');
            $table->timestamps();

            $table->index(['scheduling_agreement_id', 'schedule_date'], 'sads_sa_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sa_delivery_schedules');
        Schema::dropIfExists('scheduling_agreements');
    }
};
