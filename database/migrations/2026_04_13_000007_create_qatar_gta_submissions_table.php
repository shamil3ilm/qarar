<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qatar GTA (General Tax Authority) e-invoicing submissions.
 *
 * Qatar GTA announced mandatory e-invoicing in phases, modeled
 * on the ZATCA framework but using Qatar-specific identifiers
 * (QatarTRN — Tax Registration Number, 11 digits).
 *
 * VAT rate: 0% (Qatar does not currently impose VAT on most supplies,
 * but selective tax and excise duties apply; this table is forward-looking
 * for when Qatar introduces a broader VAT regime).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qatar_gta_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->string('invoice_number', 100);
            $table->enum('invoice_type', ['invoice', 'credit_note', 'debit_note'])->default('invoice');
            $table->date('issue_date');
            $table->string('currency_code', 3)->default('QAR');

            // Qatar TRNs (11-digit registration numbers)
            $table->string('seller_trn', 20)->nullable();
            $table->string('buyer_trn', 20)->nullable();

            // Amounts
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4)->default(0);

            // XML payload (UBL 2.1 or Qatar-specific format)
            $table->longText('invoice_xml')->nullable();
            $table->text('qr_code_data')->nullable();

            // GTA submission tracking
            $table->enum('status', ['pending', 'submitted', 'accepted', 'rejected', 'cancelled'])
                ->default('pending');
            $table->string('gta_submission_id', 100)->nullable();
            $table->text('gta_response')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();

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
        Schema::dropIfExists('qatar_gta_submissions');
    }
};
