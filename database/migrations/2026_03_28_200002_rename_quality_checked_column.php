<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rename production_logs.quality_checked → is_quality_checked
 * to follow the project-wide boolean naming convention (is_* prefix).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_logs', function (Blueprint $table): void {
            $table->renameColumn('quality_checked', 'is_quality_checked');
        });
    }

    public function down(): void
    {
        Schema::table('production_logs', function (Blueprint $table): void {
            $table->renameColumn('is_quality_checked', 'quality_checked');
        });
    }
};
