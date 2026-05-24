<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->string('segment_id', 50)->nullable()->after('cost_center_id');
            $table->string('category', 50)->nullable()->after('segment_id');
            $table->foreignId('profit_center_id')->nullable()->after('category')->constrained('profit_centers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->dropForeign(['profit_center_id']);
            $table->dropColumn(['segment_id', 'category', 'profit_center_id']);
        });
    }
};
