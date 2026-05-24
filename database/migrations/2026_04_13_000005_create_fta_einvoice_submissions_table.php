<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UAE FTA e-invoicing submissions (UBL 2.1 / EmaraTax).
 *
 * The UAE Federal Tax Authority (FTA) mandated e-invoicing using UBL 2.1 XML.
 * Each invoice or credit/debit note submitted to FTA is tracked here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fta_einvoice_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            // Invoice identifiers
            $table->string('invoice_number', 100);
            $table->enum('invoice_type', ['invoice', 'credit_note', 'debit_note'])->default('invoice');
            $table->date('issue_date');
            $table->string('currency_code', 3)->default('AED');

            // UAE Tax Registration Numbers
            $table->string('seller_trn', 20)->nullable();   // seller TRN (Tax Registration Number)
            $table->string('buyer_trn', 20)->nullable();    // buyer TRN (optional for B2C)

            // Amounts
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->decimal('tax_rate', 7, 4)->default(5.0000); // UAE VAT 5%

            // UBL 2.1 payload
            $table->longText('ubl_xml')->nullable();        // generated UBL 2.1 XML
            $table->text('qr_code_data')->nullable();       // TLV-encoded QR code

            // FTA submission status
            $table->enum('status', ['pending', 'submitted', 'accepted', 'rejected', 'cancelled'])
                ->default('pending');
            $table->string('fta_submission_id', 100)->nullable();   // FTA acknowledgement ID
            $table->text('fta_response')->nullable();               // raw FTA response
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();

            // Billing reference (for credit/debit notes)
            $table->string('billing_reference', 100)->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fta_einvoice_submissions');
    }
};
