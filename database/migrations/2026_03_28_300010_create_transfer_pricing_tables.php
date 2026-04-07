<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop in reverse FK order
        Schema::dropIfExists('transfer_price_history');
        Schema::dropIfExists('transfer_price_conditions');
        Schema::dropIfExists('transfer_price_versions');
        Schema::dropIfExists('transfer_prices');

        Schema::create('transfer_prices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('from_profit_center_id')->nullable();
            $table->unsignedBigInteger('to_profit_center_id')->nullable();
            $table->unsignedBigInteger('from_cost_center_id')->nullable();
            $table->unsignedBigInteger('to_cost_center_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('cost_element_id')->nullable();
            $table->string('transfer_price_method', 30)->default('standard_cost');
            $table->decimal('base_price', 18, 4);
            $table->decimal('markup_percentage', 8, 4)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('currency_code', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'tp_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('from_profit_center_id', 'tp_from_pc_fk')
                ->references('id')->on('profit_centers')->onDelete('set null');
            $table->foreign('to_profit_center_id', 'tp_to_pc_fk')
                ->references('id')->on('profit_centers')->onDelete('set null');
            $table->foreign('from_cost_center_id', 'tp_from_cc_fk')
                ->references('id')->on('cost_centers')->onDelete('set null');
            $table->foreign('to_cost_center_id', 'tp_to_cc_fk')
                ->references('id')->on('cost_centers')->onDelete('set null');
            $table->foreign('product_id', 'tp_product_fk')
                ->references('id')->on('products')->onDelete('set null');
            $table->foreign('cost_element_id', 'tp_cost_elem_fk')
                ->references('id')->on('cost_elements')->onDelete('set null');

            $table->index(['from_profit_center_id', 'to_profit_center_id'], 'tp_pc_idx');
            $table->index(['product_id', 'is_active'], 'tp_product_active_idx');
            $table->index(['effective_from', 'effective_to'], 'tp_effectivity_idx');
        });

        Schema::create('transfer_price_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('version_name');
            $table->smallInteger('fiscal_year');
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'tpv_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by', 'tpv_user_fk')
                ->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('transfer_price_conditions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('transfer_price_id');
            $table->unsignedBigInteger('version_id');
            $table->string('condition_type', 30);
            $table->decimal('amount', 18, 4);
            $table->boolean('is_percentage')->default(false);
            $table->timestamps();

            $table->foreign('transfer_price_id', 'tpc_tp_fk')
                ->references('id')->on('transfer_prices')->onDelete('cascade');
            $table->foreign('version_id', 'tpc_version_fk')
                ->references('id')->on('transfer_price_versions')->onDelete('cascade');
        });

        Schema::create('transfer_price_history', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('transfer_price_id');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->decimal('old_price', 18, 4);
            $table->decimal('new_price', 18, 4);
            $table->text('change_reason')->nullable();
            $table->dateTime('changed_at');
            $table->timestamps();

            $table->foreign('transfer_price_id', 'tph_tp_fk')
                ->references('id')->on('transfer_prices')->onDelete('cascade');
            $table->foreign('changed_by', 'tph_user_fk')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_price_history');
        Schema::dropIfExists('transfer_price_conditions');
        Schema::dropIfExists('transfer_price_versions');
        Schema::dropIfExists('transfer_prices');
    }
};
