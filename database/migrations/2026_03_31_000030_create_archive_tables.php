<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices_archive', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('invoice_number', 50)->nullable();
            $table->string('status', 30)->default('draft');
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $table->decimal('amount_paid', 20, 4)->default(0);
            $table->decimal('amount_due', 20, 4)->default(0);
            $table->string('currency_code', 10)->default('SAR');
            $table->string('compliance_status', 30)->nullable();
            $table->timestamp('archived_at')->useCurrent();
            $table->timestamps();
            $table->index(['organization_id', 'invoice_date']);
        });

        Schema::create('journal_entries_archive', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('fiscal_year_id')->nullable();
            $table->string('entry_number', 50)->nullable();
            $table->string('status', 30)->default('draft');
            $table->date('entry_date')->nullable();
            $table->text('description')->nullable();
            $table->decimal('total_debit', 20, 4)->default(0);
            $table->decimal('total_credit', 20, 4)->default(0);
            $table->timestamp('archived_at')->useCurrent();
            $table->timestamps();
            $table->index(['organization_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries_archive');
        Schema::dropIfExists('invoices_archive');
    }
};
