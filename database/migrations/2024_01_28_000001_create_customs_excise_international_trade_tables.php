<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // CUSTOMS TARIFF & DECLARATIONS
        // =====================================================================

        // HS (Harmonized System) tariff codes - international standard
        Schema::create('customs_tariff_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12); // HS code (2-12 digits)
            $table->string('description');
            $table->string('chapter', 2)->nullable(); // HS chapter (first 2 digits)
            $table->string('heading', 4)->nullable(); // HS heading (first 4 digits)
            $table->string('subheading', 6)->nullable(); // HS subheading (first 6 digits)
            $table->string('country_code', 3)->nullable(); // Country-specific extension (null = international)
            $table->decimal('duty_rate_percent', 8, 4)->default(0); // Ad valorem duty %
            $table->decimal('specific_duty', 15, 4)->nullable(); // Specific duty per unit
            $table->string('specific_duty_unit', 20)->nullable(); // kg, liter, piece, etc.
            $table->string('duty_type', 20)->default('ad_valorem'); // ad_valorem, specific, composite, mixed
            $table->decimal('excise_rate', 8, 4)->nullable(); // Excise duty if applicable
            $table->boolean('requires_license')->default(false); // Import/export license required
            $table->boolean('is_prohibited')->default(false); // Prohibited goods
            $table->boolean('is_restricted')->default(false); // Restricted goods
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['code']);
            $table->index(['chapter']);
            $table->index(['country_code', 'code']);
        });

        // Customs declarations (import/export)
        Schema::create('customs_declarations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('declaration_number', 50);
            $table->string('declaration_type', 20); // import, export, transit, re_export, temporary_import, temporary_export
            $table->string('customs_regime', 30)->nullable(); // free_circulation, warehousing, inward_processing, outward_processing, transit

            // Source document
            $table->string('source_type', 100)->nullable(); // PurchaseOrder, Invoice, ImportShipment
            $table->unsignedBigInteger('source_id')->nullable();

            // Parties
            $table->foreignId('importer_exporter_id')->nullable()->constrained('contacts')->nullOnDelete(); // Importer/Exporter
            $table->foreignId('broker_id')->nullable()->constrained('contacts')->nullOnDelete(); // Customs broker
            $table->string('consignee_name')->nullable();
            $table->string('consignor_name')->nullable();

            // Customs office & port
            $table->string('customs_office', 100)->nullable();
            $table->string('port_of_entry', 100)->nullable();
            $table->string('port_of_exit', 100)->nullable();

            // Origin / Destination
            $table->string('country_of_origin', 3)->nullable();
            $table->string('country_of_destination', 3)->nullable();
            $table->string('country_of_consignment', 3)->nullable();

            // Trade terms
            $table->string('incoterm', 10)->nullable(); // EXW, FOB, CIF, DDP, etc.
            $table->string('transport_mode', 20)->nullable(); // sea, air, road, rail, multimodal, postal, pipeline
            $table->string('vessel_name')->nullable();
            $table->string('voyage_flight_number', 50)->nullable();

            // Values
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 15, 8)->default(1);
            $table->decimal('fob_value', 18, 4)->default(0); // Free on Board
            $table->decimal('freight_value', 18, 4)->default(0);
            $table->decimal('insurance_value', 18, 4)->default(0);
            $table->decimal('cif_value', 18, 4)->default(0); // Cost, Insurance, Freight
            $table->decimal('assessable_value', 18, 4)->default(0); // Customs assessable value
            $table->decimal('total_duty', 18, 4)->default(0);
            $table->decimal('total_vat', 18, 4)->default(0);
            $table->decimal('total_excise', 18, 4)->default(0);
            $table->decimal('total_fees', 18, 4)->default(0); // Other customs fees
            $table->decimal('total_payable', 18, 4)->default(0);

            // Weights & packages
            $table->decimal('gross_weight_kg', 15, 4)->nullable();
            $table->decimal('net_weight_kg', 15, 4)->nullable();
            $table->unsignedInteger('total_packages')->nullable();
            $table->string('package_type', 30)->nullable(); // container, pallet, box, bulk

            // Bill of entry / shipping bill number
            $table->string('bill_of_entry_number', 50)->nullable(); // For imports
            $table->string('shipping_bill_number', 50)->nullable(); // For exports

            // Status
            $table->string('status', 20)->default('draft'); // draft, submitted, assessed, duty_paid, cleared, rejected, cancelled
            $table->date('declaration_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamp('duty_paid_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            // Journal & accounting
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'declaration_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'declaration_type']);
            $table->index(['source_type', 'source_id']);
            $table->index(['declaration_date']);
        });

        // Customs declaration line items
        Schema::create('customs_declaration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('declaration_id')->constrained('customs_declarations')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedSmallInteger('item_number')->default(1);
            $table->string('description');

            // Tariff
            $table->string('tariff_code', 12)->nullable();
            $table->foreignId('tariff_id')->nullable()->constrained('customs_tariff_codes')->nullOnDelete();

            // Quantity & weight
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 20)->nullable();
            $table->decimal('gross_weight_kg', 12, 4)->nullable();
            $table->decimal('net_weight_kg', 12, 4)->nullable();

            // Values
            $table->decimal('unit_value', 15, 4);
            $table->decimal('total_value', 18, 4);
            $table->decimal('assessable_value', 18, 4)->nullable();

            // Duties & taxes
            $table->decimal('duty_rate', 8, 4)->default(0);
            $table->decimal('duty_amount', 18, 4)->default(0);
            $table->decimal('vat_rate', 8, 4)->default(0);
            $table->decimal('vat_amount', 18, 4)->default(0);
            $table->decimal('excise_rate', 8, 4)->default(0);
            $table->decimal('excise_amount', 18, 4)->default(0);
            $table->decimal('cess_rate', 8, 4)->default(0); // India cess
            $table->decimal('cess_amount', 18, 4)->default(0);
            $table->decimal('other_charges', 18, 4)->default(0);
            $table->decimal('total_taxes', 18, 4)->default(0);

            // Origin
            $table->string('country_of_origin', 3)->nullable();
            $table->string('preferential_tariff_code', 30)->nullable(); // FTA preferential treatment
            $table->boolean('preferential_treatment')->default(false);

            $table->timestamps();

            $table->index(['declaration_id', 'item_number']);
            $table->index(['tariff_code']);
        });

        // =====================================================================
        // EXCISE DUTY
        // =====================================================================

        // Excise categories (tobacco, alcohol, sugary drinks, energy drinks, etc.)
        Schema::create('excise_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->string('country_code', 3)->nullable(); // Country-specific
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        // Excise rates per category
        Schema::create('excise_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excise_category_id')->constrained('excise_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('rate_type', 20); // percentage, specific, composite
            $table->decimal('rate_percent', 8, 4)->nullable(); // Ad valorem %
            $table->decimal('specific_amount', 15, 4)->nullable(); // Per unit amount
            $table->string('specific_unit', 20)->nullable(); // per_liter, per_kg, per_unit
            $table->string('currency_code', 3)->nullable();
            $table->string('country_code', 3)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['excise_category_id', 'effective_from']);
            $table->index(['country_code']);
        });

        // Product-excise category mapping
        Schema::create('product_excise_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('excise_category_id')->constrained('excise_categories')->cascadeOnDelete();
            $table->foreignId('excise_rate_id')->nullable()->constrained('excise_rates')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'excise_category_id']);
        });

        // Excise tax declarations / returns
        Schema::create('excise_declarations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('declaration_number', 50);
            $table->string('declaration_type', 20)->default('periodic'); // periodic, ad_hoc, amendment
            $table->date('period_from');
            $table->date('period_to');

            // Totals
            $table->decimal('total_excisable_value', 18, 4)->default(0);
            $table->decimal('total_excise_duty', 18, 4)->default(0);
            $table->decimal('total_deductions', 18, 4)->default(0); // Credits/exemptions
            $table->decimal('net_payable', 18, 4)->default(0);

            // Status
            $table->string('status', 20)->default('draft'); // draft, submitted, paid, rejected, amended
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();

            // Accounting
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'declaration_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['period_from', 'period_to']);
        });

        // Excise declaration line items
        Schema::create('excise_declaration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('declaration_id')->constrained('excise_declarations')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('excise_category_id')->constrained('excise_categories')->cascadeOnDelete();
            $table->foreignId('excise_rate_id')->nullable()->constrained('excise_rates')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 20)->nullable();
            $table->decimal('excisable_value', 18, 4);
            $table->decimal('excise_rate', 8, 4)->nullable(); // Alias for excise_rate_applied
            $table->decimal('excise_rate_applied', 8, 4)->nullable();
            $table->decimal('excise_amount', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['declaration_id']);
        });

        // =====================================================================
        // ENHANCED MULTI-CURRENCY
        // =====================================================================

        // Currency revaluation runs
        Schema::create('currency_revaluations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('revaluation_number', 30);
            $table->date('revaluation_date');
            $table->string('currency_code', 3); // The foreign currency being revalued
            $table->decimal('old_rate', 15, 8); // Previous exchange rate
            $table->decimal('new_rate', 15, 8); // New exchange rate
            $table->string('base_currency', 3); // Org's base currency

            // Impact
            $table->decimal('total_unrealized_gain', 18, 4)->default(0);
            $table->decimal('total_unrealized_loss', 18, 4)->default(0);
            $table->decimal('net_gain_loss', 18, 4)->default(0);

            // Account for gain/loss
            $table->foreignId('gain_loss_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->string('status', 20)->default('draft'); // draft, posted, reversed
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'revaluation_number']);
            $table->index(['organization_id', 'revaluation_date']);
            $table->index(['organization_id', 'currency_code']);
        });

        // Currency revaluation line items (per account affected)
        Schema::create('currency_revaluation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revaluation_id')->constrained('currency_revaluations')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->string('account_type', 30); // receivable, payable, bank, asset, liability
            $table->decimal('foreign_currency_balance', 18, 4); // Balance in foreign currency
            $table->decimal('old_base_amount', 18, 4); // Balance at old rate
            $table->decimal('new_base_amount', 18, 4); // Balance at new rate
            $table->decimal('gain_loss_amount', 18, 4); // Difference
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete(); // For AR/AP
            $table->timestamps();

            $table->index(['revaluation_id']);
            $table->index(['account_id']);
        });

        // Realized foreign exchange gains/losses (on payment/settlement)
        Schema::create('forex_gain_loss_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 20); // realized, unrealized
            $table->string('transaction_type', 20); // payment, receipt, transfer, revaluation

            // Source
            $table->string('source_type', 100); // PaymentReceived, PaymentMade, BankTransfer
            $table->unsignedBigInteger('source_id');

            // Currencies
            $table->string('foreign_currency', 3);
            $table->string('base_currency', 3);
            $table->decimal('foreign_amount', 18, 4);
            $table->decimal('original_rate', 15, 8); // Rate when invoice/bill was created
            $table->decimal('settlement_rate', 15, 8); // Rate at payment time
            $table->decimal('gain_loss_amount', 18, 4); // In base currency (positive = gain)

            // Accounting
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->date('transaction_date');
            $table->timestamps();

            $table->index(['organization_id', 'transaction_date']);
            $table->index(['source_type', 'source_id']);
            $table->index(['entry_type']);
        });

        // Organization currency settings (which currencies an org works with)
        Schema::create('organization_currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3);
            $table->boolean('is_base_currency')->default(false);
            $table->boolean('is_active')->default(true);

            // Default accounts for this currency
            $table->foreignId('exchange_gain_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('exchange_loss_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('rounding_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            // Rounding
            $table->decimal('rounding_precision', 8, 4)->default(0.01);
            $table->string('rounding_method', 10)->default('round'); // round, ceil, floor

            $table->timestamps();

            $table->unique(['organization_id', 'currency_code']);
        });

        // =====================================================================
        // INTERNATIONAL TRADE
        // =====================================================================

        // Incoterms reference table
        Schema::create('incoterms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10); // EXW, FCA, CPT, CIP, DAP, DPU, DDP, FAS, FOB, CFR, CIF
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version', 4)->default('2020'); // 2010, 2020
            $table->text('seller_responsibilities')->nullable();
            $table->text('buyer_responsibilities')->nullable();
            $table->string('risk_transfer_point')->nullable();
            $table->string('cost_transfer_point')->nullable();
            $table->string('transport_modes', 50)->nullable(); // all, sea_inland_waterway
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['code', 'version']);
        });

        // Trade documents (commercial invoice, B/L, AWB, CoO, packing list, etc.)
        Schema::create('trade_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 30); // bill_of_lading, airway_bill, certificate_of_origin, packing_list, commercial_invoice, insurance_cert, inspection_cert, phytosanitary, fumigation, customs_invoice, consular_invoice
            $table->string('document_number', 100);
            $table->string('reference', 100)->nullable();

            // Linked entity
            $table->string('source_type', 100)->nullable(); // PurchaseOrder, Invoice, ImportShipment, CustomsDeclaration
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            // Details
            $table->date('issued_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('issuing_authority')->nullable();
            $table->string('issuing_country', 3)->nullable();

            // File
            $table->string('file_path')->nullable();
            $table->string('file_type', 50)->nullable();
            $table->unsignedInteger('file_size')->nullable();

            $table->string('status', 20)->default('active'); // active, expired, cancelled, draft
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'document_type']);
            $table->index(['source_type', 'source_id']);
            $table->index(['document_number']);
        });

        // Letters of credit
        Schema::create('letters_of_credit', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('lc_number', 50);
            $table->string('lc_type', 20); // import, export, standby, revolving, transferable, back_to_back
            $table->boolean('is_irrevocable')->default(true);
            $table->boolean('is_confirmed')->default(false);

            // Banks
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('issuing_bank')->nullable();
            $table->string('issuing_bank_swift', 11)->nullable();
            $table->string('advising_bank')->nullable();
            $table->string('advising_bank_swift', 11)->nullable();
            $table->string('confirming_bank')->nullable();
            $table->string('negotiating_bank')->nullable();

            // Parties
            $table->foreignId('applicant_id')->nullable()->constrained('contacts')->nullOnDelete(); // Importer
            $table->foreignId('beneficiary_id')->nullable()->constrained('contacts')->nullOnDelete(); // Exporter

            // Financial
            $table->string('currency_code', 3);
            $table->decimal('amount', 18, 4);
            $table->decimal('tolerance_percent', 5, 2)->default(0); // +/- % tolerance
            $table->decimal('utilized_amount', 18, 4)->default(0);
            $table->decimal('available_amount', 18, 4)->default(0);

            // Dates
            $table->date('issue_date')->nullable();
            $table->date('expiry_date');
            $table->date('latest_shipment_date')->nullable();
            $table->string('place_of_expiry')->nullable();
            $table->unsignedSmallInteger('presentation_days')->default(21); // Days after shipment to present docs

            // Trade terms
            $table->string('incoterm', 10)->nullable();
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->nullable();
            $table->boolean('partial_shipments_allowed')->default(false);
            $table->boolean('transhipment_allowed')->default(false);

            // Required documents
            $table->json('required_documents')->nullable(); // List of required trade documents

            // Terms
            $table->text('terms_and_conditions')->nullable();
            $table->text('special_conditions')->nullable();

            // Status
            $table->string('status', 20)->default('draft'); // draft, applied, issued, amended, partially_utilized, fully_utilized, expired, cancelled
            $table->text('notes')->nullable();

            // Linked
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'lc_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['beneficiary_id']);
            $table->index(['applicant_id']);
            $table->index(['expiry_date']);
        });

        // LC amendments
        Schema::create('lc_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lc_id')->constrained('letters_of_credit')->cascadeOnDelete();
            $table->unsignedSmallInteger('amendment_number');
            $table->date('amendment_date');
            $table->text('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['lc_id', 'amendment_number']);
        });

        // Import/Export shipments (international shipment tracking)
        Schema::create('import_export_shipments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shipment_number', 50);
            $table->string('shipment_type', 10); // import, export

            // Source
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete(); // Supplier or Customer

            // Trade terms
            $table->string('incoterm', 10)->nullable();
            $table->string('transport_mode', 20); // sea, air, road, rail, multimodal, courier
            $table->string('vessel_name')->nullable();
            $table->string('voyage_number', 50)->nullable();
            $table->json('container_numbers')->nullable();
            $table->string('bill_of_lading', 50)->nullable();
            $table->string('airway_bill', 50)->nullable();

            // Ports
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->nullable();
            $table->string('place_of_delivery')->nullable();
            $table->string('country_of_origin', 3)->nullable();
            $table->string('country_of_destination', 3)->nullable();

            // Dates
            $table->date('estimated_departure')->nullable();
            $table->date('actual_departure')->nullable();
            $table->date('estimated_arrival')->nullable();
            $table->date('actual_arrival')->nullable();
            $table->date('delivery_date')->nullable();

            // Values
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 15, 8)->default(1);
            $table->decimal('fob_value', 18, 4)->default(0);
            $table->decimal('freight_value', 18, 4)->default(0);
            $table->decimal('insurance_value', 18, 4)->default(0);
            $table->decimal('cif_value', 18, 4)->default(0);
            $table->decimal('other_charges', 18, 4)->default(0);

            // Weights & dimensions
            $table->decimal('gross_weight_kg', 15, 4)->nullable();
            $table->decimal('net_weight_kg', 15, 4)->nullable();
            $table->unsignedInteger('total_packages')->nullable();
            $table->decimal('total_cbm', 12, 4)->nullable(); // Cubic meters

            // Linked
            $table->foreignId('customs_declaration_id')->nullable()->constrained('customs_declarations')->nullOnDelete();
            $table->foreignId('lc_id')->nullable()->constrained('letters_of_credit')->nullOnDelete();
            $table->foreignId('landed_cost_voucher_id')->nullable(); // Will be linked after creation

            // Insurance
            $table->string('insurance_policy_number')->nullable();
            $table->string('insurance_company')->nullable();

            // Status
            $table->string('status', 20)->default('pending'); // pending, in_transit, at_port, customs_clearance, cleared, delivered, cancelled
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'shipment_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'shipment_type']);
            $table->index(['contact_id']);
            $table->index(['purchase_order_id']);
            $table->index(['invoice_id']);
        });

        // Import/Export shipment items
        Schema::create('import_export_shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('import_export_shipments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 15, 4);
            $table->decimal('total_value', 18, 4);
            $table->decimal('weight_kg', 12, 4)->nullable();
            $table->string('tariff_code', 12)->nullable();
            $table->string('country_of_origin', 3)->nullable();
            $table->timestamps();

            $table->index(['shipment_id']);
        });

        // Landed cost vouchers (allocate import costs to product cost)
        Schema::create('landed_cost_vouchers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('voucher_number', 30);
            $table->date('voucher_date');

            // Source
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('import_export_shipments')->nullOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();

            // Totals
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 15, 8)->default(1);
            $table->decimal('total_purchase_value', 18, 4)->default(0);
            $table->decimal('total_additional_charges', 18, 4)->default(0);
            $table->decimal('total_landed_cost', 18, 4)->default(0);

            // Allocation
            $table->string('allocation_method', 20)->default('value'); // value, quantity, weight, volume, manual

            // Status
            $table->string('status', 20)->default('draft'); // draft, posted, cancelled
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'voucher_number']);
            $table->index(['organization_id', 'status']);
        });

        // Landed cost voucher - product items
        Schema::create('landed_cost_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('landed_cost_vouchers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('purchase_value', 18, 4); // Original purchase value
            $table->decimal('weight_kg', 12, 4)->nullable();
            $table->decimal('volume_cbm', 12, 4)->nullable();

            // Allocated charges
            $table->decimal('allocated_customs_duty', 18, 4)->default(0);
            $table->decimal('allocated_freight', 18, 4)->default(0);
            $table->decimal('allocated_insurance', 18, 4)->default(0);
            $table->decimal('allocated_clearing', 18, 4)->default(0);
            $table->decimal('allocated_other', 18, 4)->default(0);
            $table->decimal('total_additional_cost', 18, 4)->default(0);
            $table->decimal('total_landed_cost', 18, 4)->default(0);
            $table->decimal('landed_cost_per_unit', 15, 4)->default(0);

            $table->timestamps();

            $table->index(['voucher_id']);
            $table->index(['product_id']);
        });

        // Landed cost voucher - additional charges
        Schema::create('landed_cost_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('landed_cost_vouchers')->cascadeOnDelete();
            $table->string('charge_type', 30); // customs_duty, freight, insurance, clearing_charges, port_charges, handling, demurrage, inspection, fumigation, documentation, exchange_difference, other
            $table->string('description');
            $table->foreignId('vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();

            // Amount
            $table->decimal('amount', 18, 4);
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 15, 8)->default(1);
            $table->decimal('base_amount', 18, 4); // In org base currency

            // Accounting
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_allocated')->default(false);

            $table->timestamps();

            $table->index(['voucher_id']);
        });

        // Free Trade Agreement / Preferential trade
        Schema::create('trade_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->json('member_countries'); // Country codes
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['code']);
        });

        // Preferential duty rates under trade agreements
        Schema::create('preferential_duty_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_agreement_id')->constrained('trade_agreements')->cascadeOnDelete();
            $table->string('tariff_code', 12);
            $table->string('origin_country', 3); // Exporting country
            $table->string('destination_country', 3); // Importing country
            $table->decimal('preferential_rate', 8, 4); // Reduced duty rate
            $table->decimal('normal_rate', 8, 4)->nullable(); // Normal MFN rate for comparison
            $table->string('rule_of_origin')->nullable(); // What qualifies for preferential treatment
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tariff_code', 'origin_country', 'destination_country'], 'pref_duty_tariff_origin_dest_idx');
            $table->index(['trade_agreement_id']);
        });

        // Add international trade columns to existing tables
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('import_export_code', 30)->nullable()->after('tax_number'); // IEC for India, etc.
            $table->string('default_incoterm', 10)->nullable()->after('import_export_code');
            $table->string('default_port')->nullable()->after('default_incoterm');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('incoterm', 10)->nullable()->after('exchange_rate');
            $table->string('port_of_loading')->nullable()->after('incoterm');
            $table->string('port_of_discharge')->nullable()->after('port_of_loading');
            $table->string('country_of_origin', 3)->nullable()->after('port_of_discharge');
            $table->boolean('is_international')->default(false)->after('country_of_origin');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('incoterm', 10)->nullable()->after('exchange_rate');
            $table->string('port_of_loading')->nullable()->after('incoterm');
            $table->string('port_of_discharge')->nullable()->after('port_of_loading');
            $table->string('country_of_destination', 3)->nullable()->after('port_of_discharge');
            $table->boolean('is_international')->default(false)->after('country_of_destination');
            $table->boolean('is_export')->default(false)->after('is_international');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['incoterm', 'port_of_loading', 'port_of_discharge', 'country_of_destination', 'is_international', 'is_export']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['incoterm', 'port_of_loading', 'port_of_discharge', 'country_of_origin', 'is_international']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['import_export_code', 'default_incoterm', 'default_port']);
        });

        Schema::dropIfExists('preferential_duty_rates');
        Schema::dropIfExists('trade_agreements');
        Schema::dropIfExists('landed_cost_charges');
        Schema::dropIfExists('landed_cost_items');
        Schema::dropIfExists('landed_cost_vouchers');
        Schema::dropIfExists('import_export_shipment_items');
        Schema::dropIfExists('import_export_shipments');
        Schema::dropIfExists('lc_amendments');
        Schema::dropIfExists('letters_of_credit');
        Schema::dropIfExists('trade_documents');
        Schema::dropIfExists('incoterms');
        Schema::dropIfExists('organization_currencies');
        Schema::dropIfExists('forex_gain_loss_entries');
        Schema::dropIfExists('currency_revaluation_items');
        Schema::dropIfExists('currency_revaluations');
        Schema::dropIfExists('excise_declaration_items');
        Schema::dropIfExists('excise_declarations');
        Schema::dropIfExists('product_excise_mappings');
        Schema::dropIfExists('excise_rates');
        Schema::dropIfExists('excise_categories');
        Schema::dropIfExists('customs_declaration_items');
        Schema::dropIfExists('customs_declarations');
        Schema::dropIfExists('customs_tariff_codes');
    }
};
