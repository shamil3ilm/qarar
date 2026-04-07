<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_headers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('rfq_number', 30);
            $table->string('title', 200);
            $table->enum('status', ['draft', 'sent', 'closed', 'cancelled', 'awarded'])->default('draft');
            $table->date('submission_deadline')->nullable();
            $table->date('delivery_date')->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->unique(['organization_id', 'rfq_number'], 'rfq_headers_org_number_unique');
            $table->index(['organization_id', 'status'], 'rfq_headers_org_status_idx');
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rfq_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description', 500);
            $table->decimal('quantity', 15, 4);
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('rfq_id')->references('id')->on('rfq_headers')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('unit_id')->references('id')->on('units_of_measure')->nullOnDelete();

            $table->index('rfq_id', 'rfq_items_rfq_id_idx');
        });

        Schema::create('rfq_vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rfq_id');
            $table->unsignedBigInteger('contact_id');
            $table->timestamp('sent_at')->nullable();
            $table->date('response_deadline')->nullable();
            $table->enum('status', ['invited', 'responded', 'declined', 'awarded', 'rejected'])->default('invited');
            $table->timestamps();

            $table->foreign('rfq_id')->references('id')->on('rfq_headers')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts');

            $table->unique(['rfq_id', 'contact_id'], 'rfq_vendors_rfq_contact_unique');
            $table->index(['rfq_id', 'status'], 'rfq_vendors_rfq_status_idx');
        });

        Schema::create('rfq_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('rfq_id');
            $table->unsignedBigInteger('rfq_vendor_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('quote_number', 100)->nullable();
            $table->date('quote_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('currency_code', 3);
            $table->decimal('total_amount', 15, 4);
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->string('payment_terms', 200)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['received', 'evaluated', 'awarded', 'rejected'])->default('received');
            $table->timestamps();

            $table->foreign('rfq_id')->references('id')->on('rfq_headers')->onDelete('cascade');
            $table->foreign('rfq_vendor_id')->references('id')->on('rfq_vendors')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts');

            $table->index(['rfq_id', 'status'], 'rfq_quotes_rfq_status_idx');
        });

        Schema::create('rfq_quote_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rfq_quote_id');
            $table->unsignedBigInteger('rfq_item_id');
            $table->decimal('unit_price', 15, 4);
            $table->decimal('quantity', 15, 4);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 15, 4);
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('rfq_quote_id')->references('id')->on('rfq_quotes')->onDelete('cascade');
            $table->foreign('rfq_item_id')->references('id')->on('rfq_items')->onDelete('cascade');

            $table->index('rfq_quote_id', 'rfq_quote_lines_quote_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_quote_lines');
        Schema::dropIfExists('rfq_quotes');
        Schema::dropIfExists('rfq_vendors');
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('rfq_headers');
    }
};
