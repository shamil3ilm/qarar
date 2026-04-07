<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intercompany Reconciliation — SAP FI F.13 / FBICN equivalent.
 *
 * Tracks intercompany transactions between organizations (tenants) and
 * auto-matches them by reconciliation key (invoice/PO reference + amount).
 *
 * Tables:
 *  - ic_reconciliation_sessions  : per-period matching run header
 *  - ic_reconciliation_items     : individual IC transactions proposed for matching
 *  - ic_reconciliation_matches   : confirmed matched pairs (A ↔ B)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ic_reconciliation_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');   // initiating org
            $table->string('session_number', 30)->unique();  // ICR-2026-0001
            $table->string('fiscal_year', 4);
            $table->unsignedTinyInteger('period');           // 1-12
            $table->enum('status', ['draft', 'running', 'completed', 'closed'])->default('draft');
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);
            $table->decimal('matched_amount', 18, 4)->default(0);
            $table->decimal('difference_amount', 18, 4)->default(0);
            $table->unsignedBigInteger('run_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'fiscal_year', 'period']);
        });

        Schema::create('ic_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('organization_id');

            // Source transaction
            $table->string('source_type', 50);                // invoice | purchase_order | journal_entry
            $table->unsignedBigInteger('source_id');
            $table->string('reference_number', 100);          // IC key used for matching
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3)->default('SAR');
            $table->date('transaction_date');

            // Counterparty
            $table->unsignedBigInteger('counterparty_organization_id')->nullable();
            $table->string('counterparty_reference', 100)->nullable();

            $table->enum('item_type', ['payable', 'receivable']);
            $table->enum('match_status', ['unmatched', 'matched', 'disputed', 'excluded'])->default('unmatched');
            $table->unsignedBigInteger('match_id')->nullable();  // -> ic_reconciliation_matches

            $table->timestamps();

            $table->index(['session_id', 'match_status']);
            $table->index(['organization_id', 'reference_number']);
            $table->foreign('session_id')->references('id')->on('ic_reconciliation_sessions')->cascadeOnDelete();
        });

        Schema::create('ic_reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('receivable_item_id');  // -> ic_reconciliation_items
            $table->unsignedBigInteger('payable_item_id');     // -> ic_reconciliation_items
            $table->decimal('receivable_amount', 18, 4);
            $table->decimal('payable_amount', 18, 4);
            $table->decimal('difference', 18, 4)->default(0);  // payable - receivable
            $table->string('currency', 3)->default('SAR');
            $table->enum('match_type', ['auto', 'manual'])->default('auto');
            $table->enum('status', ['proposed', 'confirmed', 'disputed'])->default('proposed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ic_reconciliation_matches');
        Schema::dropIfExists('ic_reconciliation_items');
        Schema::dropIfExists('ic_reconciliation_sessions');
    }
};
