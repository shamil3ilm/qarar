<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Approval delegation rules
        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegate_to')->constrained('users')->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 500)->nullable();

            // Optional: only delegate specific workflow types
            $table->string('approvable_type', 100)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
    }
};
