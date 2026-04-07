<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('skip_lot_decisions');
        Schema::dropIfExists('skip_lot_sampling_plans');

        Schema::create('skip_lot_sampling_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('plan_code', 30);
            $table->string('plan_name', 100);
            $table->enum('plan_type', ['skip_lot', 'reduced', 'normal', 'tightened'])->default('skip_lot');
            $table->unsignedTinyInteger('inspection_frequency')->default(1);
            $table->decimal('sample_size_percent', 5, 2)->default(100);
            $table->unsignedSmallInteger('accept_number')->default(0);
            $table->unsignedSmallInteger('reject_number')->default(1);
            $table->unsignedTinyInteger('switch_rule_reduced_to_normal')->nullable();
            $table->unsignedTinyInteger('switch_rule_normal_to_tightened')->nullable();
            $table->unsignedTinyInteger('switch_rule_tightened_to_rejected')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'plan_code'], 'slsp_org_code_unq');
        });

        Schema::create('skip_lot_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'sld_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('skip_lot_sampling_plan_id');
            $table->foreign('skip_lot_sampling_plan_id', 'sld_plan_fk')->references('id')->on('skip_lot_sampling_plans');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->foreign('vendor_id', 'sld_vendor_fk')->references('id')->on('contacts');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'sld_product_fk')->references('id')->on('products');
            $table->enum('current_level', ['skip_lot', 'reduced', 'normal', 'tightened', 'rejected'])->default('normal');
            $table->unsignedInteger('lots_inspected_at_level')->default(0);
            $table->unsignedInteger('consecutive_accepted')->default(0);
            $table->unsignedInteger('consecutive_rejected')->default(0);
            $table->unsignedBigInteger('last_inspection_lot_id')->nullable();
            $table->foreign('last_inspection_lot_id', 'sld_last_lot_fk')->references('id')->on('inspection_lots');
            $table->dateTime('last_evaluated_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'vendor_id', 'product_id'], 'sld_org_vendor_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skip_lot_decisions');
        Schema::dropIfExists('skip_lot_sampling_plans');
    }
};
