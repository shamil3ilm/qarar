<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('co_assessment_cycles', function (Blueprint $table): void {
            $table->boolean('copa_enabled')->default(false)->after('executed_by');
            $table->foreignId('copa_segment_id')
                ->nullable()
                ->after('copa_enabled')
                ->constrained('profitability_segments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('co_assessment_cycles', function (Blueprint $table): void {
            $table->dropForeignIdFor(\App\Models\Accounting\ProfitabilitySegment::class, 'copa_segment_id');
            $table->dropColumn(['copa_enabled', 'copa_segment_id']);
        });
    }
};
