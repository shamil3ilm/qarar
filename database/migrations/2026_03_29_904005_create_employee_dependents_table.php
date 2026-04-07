<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_dependents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('relationship', ['spouse', 'child', 'parent', 'sibling', 'other']);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->char('nationality', 2)->nullable();
            $table->enum('id_type', ['national_id', 'passport', 'birth_certificate'])->nullable();
            $table->string('id_number', 100)->nullable();
            $table->date('id_expiry_date')->nullable();
            $table->boolean('is_beneficiary')->default(false);
            $table->boolean('is_sponsored')->default(false);
            $table->string('visa_number', 50)->nullable();
            $table->date('visa_expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'employee_id']);
            $table->index(['organization_id', 'is_beneficiary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_dependents');
    }
};
