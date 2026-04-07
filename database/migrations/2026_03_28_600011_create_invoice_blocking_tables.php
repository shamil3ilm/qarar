<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('mm_invoice_tolerance_rules');
        Schema::dropIfExists('mm_parked_invoices');
        Schema::dropIfExists('mm_invoice_blocks');

        Schema::create('mm_invoice_blocks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('bill_id');
            $table->foreign('bill_id', 'mm_inv_blk_bill_fk')->references('id')->on('bills');
            $table->enum('block_type', ['manual', 'tolerance', 'price', 'quantity', 'date', 'stochastic']);
            $table->text('block_reason')->nullable();
            $table->unsignedBigInteger('blocked_by')->nullable();
            $table->foreign('blocked_by', 'mm_inv_blk_usr_fk')->references('id')->on('users');
            $table->timestamp('blocked_at');
            $table->unsignedBigInteger('released_by')->nullable();
            $table->foreign('released_by', 'mm_inv_blk_rel_usr_fk')->references('id')->on('users');
            $table->timestamp('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->enum('status', ['blocked', 'released', 'cleared'])->default('blocked');
            $table->timestamps();
        });

        Schema::create('mm_parked_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id')->references('id')->on('contacts');
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date');
            $table->date('posting_date');
            $table->decimal('total_amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->foreign('purchase_order_id', 'mm_park_inv_po_fk')->references('id')->on('purchase_orders');
            $table->unsignedBigInteger('parked_by');
            $table->foreign('parked_by')->references('id')->on('users');
            $table->timestamp('parked_at');
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->foreign('posted_by', 'mm_park_inv_post_usr_fk')->references('id')->on('users');
            $table->timestamp('posted_at')->nullable();
            $table->enum('status', ['parked', 'posted', 'cancelled'])->default('parked');
            $table->json('line_items');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('mm_invoice_tolerance_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('rule_name');
            $table->enum('tolerance_type', ['price', 'quantity', 'date', 'amount']);
            $table->enum('comparison_operator', ['absolute', 'percentage']);
            $table->decimal('lower_tolerance', 10, 4)->nullable();
            $table->decimal('upper_tolerance', 10, 4)->nullable();
            $table->boolean('auto_block')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_invoice_tolerance_rules');
        Schema::dropIfExists('mm_parked_invoices');
        Schema::dropIfExists('mm_invoice_blocks');
    }
};
