<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renames WorkOrder statuses to SAP PP terminology:
 *   pending   → released   (awaiting shop-floor release)
 *   scheduled → released   (merge into single released state)
 *
 * Adds 'closed' as a terminal state after 'completed'.
 *
 * The work_orders.status column is a VARCHAR — no ALTER COLUMN needed,
 * only data updates and an enum-style CHECK constraint update if present.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Merge pending + scheduled → released
        DB::statement("
            UPDATE work_orders
            SET status = 'released'
            WHERE status IN ('pending', 'scheduled')
        ");
    }

    public function down(): void
    {
        // Restore released → pending (cannot distinguish original pending vs scheduled)
        DB::statement("
            UPDATE work_orders
            SET status = 'pending'
            WHERE status = 'released'
        ");

        // closed → completed (best-effort rollback)
        DB::statement("
            UPDATE work_orders
            SET status = 'completed'
            WHERE status = 'closed'
        ");
    }
};
