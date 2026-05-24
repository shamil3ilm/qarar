<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * India e-invoice submissions (IRP / IRN).
 *
 * Under GST, businesses with annual turnover above ₹5 Crore must report invoices
 * to the Invoice Registration Portal (IRP). The IRP generates an
 * Invoice Reference Number (IRN) — a 64-character SHA-256 hash of
 * GSTIN + doc_type + doc_number + doc_date.
 *
 * The signed e-invoice JSON and a QR code are returned by the IRP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('india_einvoice_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            // Document identifiers
            $table->string('document_number', 100);
            $table->enum('document_type', ['INV', 'CRN', 'DBN'])->default('INV'); // INV/credit/debit
            $table->date('document_date');
            $table->string('gstin_seller', 15);    // 15-char GSTIN
            $table->string('gstin_buyer', 15)->nullable();

            // Parties
            $table->string('seller_name', 200)->nullable();
            $table->string('buyer_name', 200)->nullable();
            $table->string('seller_state_code', 2)->nullable();   // 2-digit state code
            $table->string('buyer_state_code', 2)->nullable();

            // Amounts (INR)
            $table->decimal('taxable_value', 18, 2)->default(0);
            $table->decimal('cgst_amount', 18, 2)->default(0);
            $table->decimal('sgst_amount', 18, 2)->default(0);
            $table->decimal('igst_amount', 18, 2)->default(0);
            $table->decimal('cess_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);

            // IRP response
            $table->string('irn', 64)->nullable()->unique();        // 64-char SHA-256 hash
            $table->text('signed_invoice')->nullable();             // IRP-signed JSON
            $table->text('signed_qr_code')->nullable();             // base64 QR
            $table->longText('einvoice_json')->nullable();          // full e-invoice payload

            // Submission status
            $table->enum('status', ['pending', 'submitted', 'accepted', 'rejected', 'cancelled'])
                ->default('pending');
            $table->string('irp_ack_number', 50)->nullable();
            $table->timestamp('irp_ack_date')->nullable();
            $table->text('irp_response')->nullable();

            // Cancellation
            $table->string('cancel_reason', 200)->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'document_number']);
            $table->index(['gstin_seller', 'document_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('india_einvoice_submissions');
    }
};
