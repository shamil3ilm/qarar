<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('gstin', 15);
            $table->string('state_code', 2);
            $table->string('legal_name', 200);
            $table->string('trade_name', 200)->nullable();
            $table->date('registration_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'gstin'], 'gst_reg_org_gstin_unq');
            $table->index('organization_id', 'gst_reg_org_id_idx');
            $table->index('gstin', 'gst_reg_gstin_idx');
        });

        Schema::create('gstr1_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('gstin_id');
            $table->tinyInteger('period_month')->unsigned();
            $table->smallInteger('period_year')->unsigned();
            $table->enum('filing_type', ['monthly', 'quarterly'])->default('monthly');
            $table->enum('status', ['draft', 'ready', 'filed', 'amended'])->default('draft');
            $table->decimal('total_taxable_value', 15, 4)->default(0);
            $table->decimal('total_igst', 15, 4)->default(0);
            $table->decimal('total_cgst', 15, 4)->default(0);
            $table->decimal('total_sgst', 15, 4)->default(0);
            $table->decimal('total_cess', 15, 4)->default(0);
            $table->timestamp('filed_at')->nullable();
            $table->string('arn', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'gstr1_org_id_idx');
            $table->index('gstin_id', 'gstr1_gstin_id_idx');
            $table->unique(['gstin_id', 'period_month', 'period_year', 'filing_type'], 'gstr1_period_unq');

            $table->foreign('gstin_id', 'gstr1_gstin_fk')
                ->references('id')
                ->on('gst_registrations')
                ->onDelete('restrict');
        });

        Schema::create('gstr1_b2b_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gstr1_return_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('buyer_gstin', 15);
            $table->date('invoice_date');
            $table->string('invoice_number', 50);
            $table->decimal('invoice_value', 15, 4)->default(0);
            $table->string('place_of_supply', 2);
            $table->decimal('taxable_value', 15, 4)->default(0);
            $table->decimal('igst', 15, 4)->default(0);
            $table->decimal('cgst', 15, 4)->default(0);
            $table->decimal('sgst', 15, 4)->default(0);
            $table->decimal('cess', 15, 4)->default(0);
            $table->timestamps();

            $table->index('gstr1_return_id', 'gstr1_b2b_return_id_idx');
            $table->index('buyer_gstin', 'gstr1_b2b_buyer_gstin_idx');
            $table->index('invoice_id', 'gstr1_b2b_invoice_id_idx');

            $table->foreign('gstr1_return_id', 'gstr1_b2b_return_fk')
                ->references('id')
                ->on('gstr1_returns')
                ->onDelete('cascade');
        });

        Schema::create('gstr3b_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('gstin_id');
            $table->tinyInteger('period_month')->unsigned();
            $table->smallInteger('period_year')->unsigned();
            $table->decimal('outward_taxable_supplies', 15, 4)->default(0);
            $table->decimal('outward_zero_rated', 15, 4)->default(0);
            $table->decimal('inward_supplies_itc', 15, 4)->default(0);
            $table->decimal('net_tax_payable', 15, 4)->default(0);
            $table->enum('status', ['draft', 'ready', 'filed', 'amended'])->default('draft');
            $table->timestamp('filed_at')->nullable();
            $table->string('arn', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'gstr3b_org_id_idx');
            $table->index('gstin_id', 'gstr3b_gstin_id_idx');
            $table->unique(['gstin_id', 'period_month', 'period_year'], 'gstr3b_period_unq');

            $table->foreign('gstin_id', 'gstr3b_gstin_fk')
                ->references('id')
                ->on('gst_registrations')
                ->onDelete('restrict');
        });

        Schema::create('itc_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('gstin_id');
            $table->tinyInteger('period_month')->unsigned();
            $table->smallInteger('period_year')->unsigned();
            $table->decimal('igst_available', 15, 4)->default(0);
            $table->decimal('cgst_available', 15, 4)->default(0);
            $table->decimal('sgst_available', 15, 4)->default(0);
            $table->decimal('igst_utilized', 15, 4)->default(0);
            $table->decimal('cgst_utilized', 15, 4)->default(0);
            $table->decimal('sgst_utilized', 15, 4)->default(0);
            $table->decimal('igst_closing', 15, 4)->default(0);
            $table->decimal('cgst_closing', 15, 4)->default(0);
            $table->decimal('sgst_closing', 15, 4)->default(0);
            $table->timestamps();

            $table->index('organization_id', 'itc_ledger_org_id_idx');
            $table->index('gstin_id', 'itc_ledger_gstin_id_idx');
            $table->unique(['gstin_id', 'period_month', 'period_year'], 'itc_ledger_period_unq');

            $table->foreign('gstin_id', 'itc_ledger_gstin_fk')
                ->references('id')
                ->on('gst_registrations')
                ->onDelete('restrict');
        });

        Schema::create('ewaybills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('ewb_number', 20)->nullable()->unique();
            $table->string('gstin_supplier', 15);
            $table->string('gstin_recipient', 15);
            $table->string('supply_type', 20);
            $table->string('transporter_id', 15)->nullable();
            $table->string('vehicle_number', 15)->nullable();
            $table->unsignedInteger('distance_km')->default(0);
            $table->enum('status', ['generated', 'cancelled', 'expired'])->default('generated');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'ewb_org_id_idx');
            $table->index(['source_type', 'source_id'], 'ewb_source_idx');
            $table->index('status', 'ewb_status_idx');
            $table->index('gstin_supplier', 'ewb_gstin_supplier_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ewaybills');
        Schema::dropIfExists('itc_ledger');
        Schema::dropIfExists('gstr3b_returns');
        Schema::dropIfExists('gstr1_b2b_invoices');
        Schema::dropIfExists('gstr1_returns');
        Schema::dropIfExists('gst_registrations');
    }
};
