<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_idempotency_keys', function (Blueprint $table): void {
            $table->id();

            // Scoped key: the caller-supplied string (e.g. "invoice:42:send")
            $table->string('key', 255);

            // Logical operation name (e.g. "invoice.send", "payroll.generate")
            $table->string('operation', 100);

            // Tenant scope — prevents cross-org key collisions
            $table->unsignedBigInteger('organization_id');

            // Processing state
            $table->enum('status', ['processing', 'completed', 'failed'])
                ->default('processing');

            // SHA-256 of the request payload for integrity checking (optional)
            $table->string('request_hash', 64)->nullable();

            // Cached result returned to duplicate callers
            $table->json('response_payload')->nullable();

            // When this key expires and may be reused
            $table->timestamp('expires_at')->index();

            $table->timestamps();

            // Serialisation point: DB enforces uniqueness, not application code
            $table->unique(['key', 'organization_id', 'operation'], 'fin_idempotency_scope_unique');

            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_idempotency_keys');
    }
};
