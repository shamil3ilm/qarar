<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aml_suspicious_activities', function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('aml_suspicious_activities', function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
        });
    }
};
