<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add ZATCA tax exemption fields to invoice_lines.
 *
 * Required for zero-rated (Z), exempt (E), and out-of-scope (O) line items.
 * ZATCA Phase 2 requires an exemption reason code and reason text when the
 * tax category is anything other than S (Standard rated).
 *
 * Migration naming note: the initial project migrations use 2024_01_* as
 * sequential ordering numbers (not actual dates). New migrations added in
 * 2026 use the actual creation date (2026_03_17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            // VATEX-SA-* exemption reason codes (e.g. VATEX-SA-29-7, VATEX-SA-HEA)
            $table->string('tax_exemption_code', 30)->nullable()->after('tax_code');
            // Human-readable reason text required alongside the code
            $table->string('tax_exemption_reason', 255)->nullable()->after('tax_exemption_code');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['tax_exemption_code', 'tax_exemption_reason']);
        });
    }
};
