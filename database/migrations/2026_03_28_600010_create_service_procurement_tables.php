<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('service_acceptances');
        Schema::dropIfExists('service_entry_sheet_lines');
        Schema::dropIfExists('service_entry_sheets');
        Schema::dropIfExists('service_po_lines');
        Schema::dropIfExists('service_purchase_orders');

        Schema::create('service_purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('po_number')->unique();
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id')->references('id')->on('contacts');
            $table->text('description');
            $table->decimal('total_value', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->enum('status', ['draft', 'sent', 'partially_accepted', 'accepted', 'closed', 'cancelled'])->default('draft');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_po_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_purchase_order_id');
            $table->foreign('service_purchase_order_id', 'svc_po_line_po_fk')->references('id')->on('service_purchase_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->text('service_description');
            $table->string('service_number')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->string('uom', 20);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('total_price', 18, 4);
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->foreign('cost_center_id', 'svc_po_line_cc_fk')->references('id')->on('cost_centers')->nullOnDelete();
            $table->unsignedBigInteger('internal_order_id')->nullable();
            $table->foreign('internal_order_id', 'svc_po_line_io_fk')->references('id')->on('internal_orders')->nullOnDelete();
            $table->decimal('accepted_quantity', 18, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('service_entry_sheets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('ses_number')->unique();
            $table->unsignedBigInteger('service_purchase_order_id');
            $table->foreign('service_purchase_order_id', 'ses_po_fk')->references('id')->on('service_purchase_orders');
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id')->references('id')->on('contacts');
            $table->date('service_period_from');
            $table->date('service_period_to');
            $table->text('description');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'posted'])->default('draft');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->foreign('submitted_by')->references('id')->on('users');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_entry_sheet_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_entry_sheet_id');
            $table->foreign('service_entry_sheet_id', 'ses_line_ses_fk')->references('id')->on('service_entry_sheets')->cascadeOnDelete();
            $table->unsignedBigInteger('service_po_line_id');
            $table->foreign('service_po_line_id', 'ses_line_pol_fk')->references('id')->on('service_po_lines');
            $table->decimal('actual_quantity', 18, 4);
            $table->string('uom', 20);
            $table->decimal('actual_price', 18, 4);
            $table->decimal('total_amount', 18, 4);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('service_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('service_entry_sheet_id')->unique();
            $table->foreign('service_entry_sheet_id', 'svc_acc_ses_fk')->references('id')->on('service_entry_sheets');
            $table->unsignedBigInteger('accepted_by');
            $table->foreign('accepted_by')->references('id')->on('users');
            $table->timestamp('accepted_at');
            $table->text('rejection_reason')->nullable();
            $table->enum('status', ['accepted', 'rejected']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_acceptances');
        Schema::dropIfExists('service_entry_sheet_lines');
        Schema::dropIfExists('service_entry_sheets');
        Schema::dropIfExists('service_po_lines');
        Schema::dropIfExists('service_purchase_orders');
    }
};
