<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eway_bills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('eway_bill_number', 50)->nullable();
            $table->string('gstin_supplier', 20)->nullable();
            $table->string('gstin_recipient', 20)->nullable();
            $table->string('transport_mode', 20)->nullable();
            $table->string('vehicle_number', 30)->nullable();
            $table->string('transporter_id', 50)->nullable();
            $table->string('transporter_doc_number', 100)->nullable();
            $table->decimal('taxable_value', 15, 2)->default(0);
            $table->decimal('cgst_value', 15, 2)->default(0);
            $table->decimal('sgst_value', 15, 2)->default(0);
            $table->decimal('igst_value', 15, 2)->default(0);
            $table->decimal('cess_value', 15, 2)->default(0);
            $table->enum('status', ['active', 'cancelled', 'expired'])->default('active');
            $table->timestamp('valid_upto')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 200)->nullable();
            $table->text('raw_response')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'eway_bills_org_status_idx');
            $table->index(['organization_id', 'invoice_id'], 'eway_bills_org_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eway_bills');
    }
};
