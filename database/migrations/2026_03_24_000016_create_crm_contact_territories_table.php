<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contact_territories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('territory_id')->constrained('territories')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['territory_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contact_territories');
    }
};
