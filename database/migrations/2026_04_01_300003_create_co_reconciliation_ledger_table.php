<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CO Reconciliation Ledger — SAP KALC equivalent.
 *
 * When a CO posting crosses company codes (e.g. an assessment cycle sender
 * in company A, receiver in company B), SAP automatically generates FI
 * reconciliation documents to keep FI and CO in balance.  This table
 * records those generated FI reconciliation entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('co_reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('run_number')->unique();              // KALC-2026-001
            $table->string('source_type');                      // assessment|distribution|activity_confirmation
            $table->unsignedBigInteger('source_id');            // FK to the CO posting (polymorphic by source_type)
            $table->string('fiscal_year', 4);
            $table->string('period', 2);
            $table->string('status')->default('pending');       // pending|posted|reversed
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('co_reconciliation_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('reconciliation_run_id');
            $table->string('entry_type');                       // debit|credit
            $table->unsignedBigInteger('sender_company_id');
            $table->unsignedBigInteger('receiver_company_id');
            $table->unsignedBigInteger('sender_cost_center_id');
            $table->unsignedBigInteger('receiver_cost_center_id');
            $table->unsignedBigInteger('cost_element_id');
            $table->unsignedBigInteger('journal_entry_id')->nullable();  // generated FI document
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('SAR');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('reconciliation_run_id')->references('id')->on('co_reconciliation_runs')->cascadeOnDelete();
            $table->index(['organization_id', 'reconciliation_run_id'], 'co_recon_entries_org_run_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('co_reconciliation_entries');
        Schema::dropIfExists('co_reconciliation_runs');
    }
};
