<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('succession_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('current_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('position_title')->nullable();
            $table->string('criticality', 20)->default('medium'); // critical, high, medium, low
            $table->string('status', 20)->default('active'); // active, inactive, completed
            $table->date('target_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'criticality']);
            $table->index('current_employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('succession_plans');
    }
};
