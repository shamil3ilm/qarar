<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed TDS Section 194Q — TDS on purchase of goods.
 *
 * Finance Act 2021, effective 1 July 2021:
 *   - Buyer must deduct TDS @ 0.1% on purchase of goods > ₹50 lakh
 *     per financial year (April – March) from a resident seller.
 *   - Applicable only when buyer's own turnover > ₹10 crore in the
 *     preceding financial year.
 *   - No PAN rate: 5% (CBDT circular).
 *   - Does NOT apply if seller is covered under Section 206C(1H) TCS.
 *
 * Also seed Section 206C(1H) for the seller-side TCS counterpart.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing194Q = DB::table('tds_sections')->where('section_code', '194Q')->first();

        if (!$existing194Q) {
            DB::table('tds_sections')->insert([
                'section_code'     => '194Q',
                'description'      => 'TDS on Purchase of Goods (Finance Act 2021)',
                'threshold_amount' => 5_000_000.00,  // ₹50 lakh per FY
                'rate_individual'  => 0.10,
                'rate_company'     => 0.10,
                'rate_no_pan'      => 5.00,
                'is_active'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        $existing206C = DB::table('tds_sections')->where('section_code', '206C(1H)')->first();

        if (!$existing206C) {
            DB::table('tds_sections')->insert([
                'section_code'     => '206C(1H)',
                'description'      => 'TCS on Sale of Goods by Seller (effective 1 Oct 2020)',
                'threshold_amount' => 5_000_000.00,  // ₹50 lakh per FY
                'rate_individual'  => 0.10,
                'rate_company'     => 0.10,
                'rate_no_pan'      => 1.00,
                'is_active'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tds_sections')->whereIn('section_code', ['194Q', '206C(1H)'])->delete();
    }
};
