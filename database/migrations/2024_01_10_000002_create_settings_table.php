<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Organization settings
        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('group', 50); // general, invoice, inventory, hr, etc.
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, integer, boolean, json, date
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false); // Can be exposed to frontend
            $table->timestamps();

            $table->unique(['organization_id', 'group', 'key']);
            $table->index(['organization_id', 'group']);
        });

        // User preferences
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('organization_settings');
    }
};
