<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('om_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('task_code', 20);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('task_type', ['function', 'activity', 'responsibility'])->default('function');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'task_code']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('om_position_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('om_tasks')->cascadeOnDelete();
            $table->enum('responsibility_level', ['primary', 'secondary', 'additional'])->default('primary');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->unique(['position_id', 'task_id']);
            $table->index(['organization_id', 'position_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('om_position_tasks');
        Schema::dropIfExists('om_tasks');
    }
};
