<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_versions', function (Blueprint $table): void {
            $table->foreignId('released_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable()->after('released_by');
        });
    }

    public function down(): void
    {
        Schema::table('costing_versions', function (Blueprint $table): void {
            $table->dropForeign(['released_by']);
            $table->dropColumn(['released_by', 'released_at']);
        });
    }
};
