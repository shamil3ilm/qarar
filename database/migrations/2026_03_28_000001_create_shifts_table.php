<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->boolean('is_overnight')->default(false);
            $table->boolean('is_flexible')->default(false);
            $table->unsignedSmallInteger('flexible_start_window_minutes')->default(0);
            $table->boolean('overtime_eligible')->default(false);
            $table->string('color_hex', 7)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active'], 'shifts_org_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
