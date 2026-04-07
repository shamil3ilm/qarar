<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_download_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('document_type', 20); // 'invoice', 'bill', 'receipt', 'payslip'
            $table->unsignedBigInteger('document_id');
            $table->foreignId('generated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('expires_at');
            $table->dateTime('first_accessed_at')->nullable();
            $table->dateTime('access_expires_at')->nullable();
            $table->smallInteger('access_count')->default(0);
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();

            $table->index('token');
            $table->index(['document_type', 'document_id']);
            $table->index(['organization_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_download_tokens');
    }
};
