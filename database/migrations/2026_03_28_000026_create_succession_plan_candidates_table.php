<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('succession_plan_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('succession_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('readiness', 30)->default('development_needed'); // ready_now, ready_1_year, ready_2_years, development_needed
            $table->unsignedTinyInteger('rank')->default(1);
            $table->text('strengths')->nullable();
            $table->text('development_areas')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['succession_plan_id', 'employee_id']);
            $table->index(['succession_plan_id', 'readiness']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('succession_plan_candidates');
    }
};
