<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_advance_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('request_number', 30);
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->decimal('requested_amount', 15, 4);
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 10, 6)->default(1);
            $table->text('purpose')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->enum('status', ['draft', 'approved', 'paid', 'cleared', 'cancelled'])->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->unique(['organization_id', 'request_number'], 'vendor_adv_req_org_number_unique');
            $table->index(['organization_id', 'status'], 'vendor_adv_req_org_status_idx');
            $table->index(['organization_id', 'contact_id'], 'vendor_adv_req_org_contact_idx');
        });

        Schema::create('vendor_advance_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('advance_request_id');
            $table->date('payment_date');
            $table->decimal('amount', 15, 4);
            $table->string('payment_method', 50);
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('advance_request_id')->references('id')->on('vendor_advance_requests')->onDelete('cascade');
            $table->foreign('bank_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();

            $table->index('advance_request_id', 'vendor_adv_pay_req_id_idx');
        });

        Schema::create('vendor_advance_clearings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('advance_payment_id');
            $table->unsignedBigInteger('bill_id');
            $table->decimal('cleared_amount', 15, 4);
            $table->date('clearing_date');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('advance_payment_id')->references('id')->on('vendor_advance_payments')->onDelete('cascade');
            $table->foreign('bill_id')->references('id')->on('bills');

            $table->index('advance_payment_id', 'vendor_adv_clr_payment_id_idx');
            $table->index('bill_id', 'vendor_adv_clr_bill_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_advance_clearings');
        Schema::dropIfExists('vendor_advance_payments');
        Schema::dropIfExists('vendor_advance_requests');
    }
};
