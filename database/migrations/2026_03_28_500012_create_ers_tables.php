<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ers_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id', 'ers_config_vendor_fk')->references('id')->on('contacts');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('auto_post')->default(false);
            $table->decimal('tolerance_percent', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'vendor_id'], 'ers_config_org_vendor_unq');
        });

        Schema::create('ers_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'ers_run_org_fk')->references('id')->on('organizations');
            $table->date('run_date');
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedBigInteger('run_by')->nullable();
            $table->foreign('run_by', 'ers_run_by_fk')->references('id')->on('users');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ers_run_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'ers_item_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('ers_run_id');
            $table->foreign('ers_run_id', 'ers_item_run_fk')->references('id')->on('ers_runs');
            $table->unsignedBigInteger('goods_receipt_id');
            $table->foreign('goods_receipt_id', 'ers_item_gr_fk')->references('id')->on('goods_receipts');
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->foreign('bill_id', 'ers_item_bill_fk')->references('id')->on('bills');
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id', 'ers_item_vendor_fk')->references('id')->on('contacts');
            $table->decimal('gross_amount', 18, 4);
            $table->enum('status', ['processed', 'failed', 'skipped'])->default('processed');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['ers_run_id'], 'ers_item_run_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ers_run_items');
        Schema::dropIfExists('ers_runs');
        Schema::dropIfExists('ers_configurations');
    }
};
