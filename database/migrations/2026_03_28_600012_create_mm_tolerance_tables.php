<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('mm_tolerance_check_results');
        Schema::dropIfExists('mm_tolerance_keys');

        Schema::create('mm_tolerance_keys', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('tolerance_key', 4);
            $table->string('description');
            $table->timestamps();

            $table->unique(['organization_id', 'tolerance_key']);
        });

        Schema::create('mm_tolerance_check_results', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('bill_id');
            $table->foreign('bill_id', 'mm_tol_chk_bill_fk')->references('id')->on('bills');
            $table->unsignedBigInteger('tolerance_key_id');
            $table->foreign('tolerance_key_id', 'mm_tol_chk_key_fk')->references('id')->on('mm_tolerance_keys');
            $table->enum('check_type', ['price', 'quantity', 'date', 'amount']);
            $table->decimal('expected_value', 18, 4)->nullable();
            $table->decimal('actual_value', 18, 4)->nullable();
            $table->decimal('deviation', 18, 4)->nullable();
            $table->decimal('deviation_pct', 8, 4)->nullable();
            $table->enum('result', ['pass', 'warning', 'block']);
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_tolerance_check_results');
        Schema::dropIfExists('mm_tolerance_keys');
    }
};
