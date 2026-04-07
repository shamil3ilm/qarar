<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('q_info_records');

        Schema::create('q_info_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->foreign('vendor_id', 'qir_vendor_fk')->references('id')->on('contacts');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'qir_product_fk')->references('id')->on('products');
            $table->enum('inspection_type', ['goods_receipt', 'in_process', 'final', 'delivery', 'returns'])->default('goods_receipt');
            $table->unsignedBigInteger('skip_lot_plan_id')->nullable();
            $table->foreign('skip_lot_plan_id', 'qir_slsp_fk')->references('id')->on('skip_lot_sampling_plans');
            $table->unsignedBigInteger('quality_plan_id')->nullable();
            $table->foreign('quality_plan_id', 'qir_qp_fk')->references('id')->on('quality_plans');
            $table->boolean('is_active')->default(true);
            $table->boolean('release_required')->default(false);
            $table->boolean('cert_required')->default(false);
            $table->string('cert_type', 50)->nullable();
            $table->unsignedSmallInteger('inspection_interval_days')->nullable();
            $table->date('last_inspection_date')->nullable();
            $table->date('next_inspection_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['organization_id', 'vendor_id', 'product_id', 'inspection_type'],
                'qir_org_vendor_prod_type_unq'
            );
            $table->index(['organization_id', 'product_id'], 'qir_org_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('q_info_records');
    }
};
