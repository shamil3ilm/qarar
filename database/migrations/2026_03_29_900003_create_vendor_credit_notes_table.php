<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('credit_note_number', 100);
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->date('issue_date');
            $table->date('credit_date');
            $table->enum('status', ['draft', 'posted', 'applied', 'void'])->default('draft');
            $table->string('reason', 255)->nullable();
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->decimal('applied_amount', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('contacts');
            $table->foreign('bill_id')->references('id')->on('bills')->nullOnDelete();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('voided_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'vendor_id', 'status']);
            $table->index(['organization_id', 'credit_date']);
        });

        Schema::create('vendor_credit_note_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('vendor_credit_note_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description', 500);
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('vendor_credit_note_id')->references('id')->on('vendor_credit_notes')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_credit_note_lines');
        Schema::dropIfExists('vendor_credit_notes');
    }
};
