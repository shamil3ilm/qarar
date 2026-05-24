<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_split_items', function (Blueprint $table): void {
            $table->string('segment_id', 50)->nullable()->after('cost_center_id');
            $table->string('split_method', 30)->default('profit_center')->after('segment_id');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_split_items', function (Blueprint $table): void {
            $table->dropColumn(['segment_id', 'split_method']);
        });
    }
};
