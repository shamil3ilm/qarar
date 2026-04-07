<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_archives', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('entry_number', 50)->nullable();
            $table->string('type', 50)->nullable();
            $table->string('reference', 255)->nullable();
            $table->string('description')->nullable();
            $table->date('entry_date');
            $table->decimal('total_debit', 20, 4)->default(0);
            $table->decimal('total_credit', 20, 4)->default(0);
            $table->string('status', 20)->default('posted');
            $table->string('currency_code', 10)->default('SAR');
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();
            $table->index(['organization_id', 'entry_date']);
            $table->index('archived_at');
        });

        Schema::create('invoice_archives', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('invoice_number', 50)->nullable();
            $table->string('status', 30)->default('paid');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
            $table->decimal('amount_paid', 20, 4)->default(0);
            $table->decimal('amount_due', 20, 4)->default(0);
            $table->string('currency_code', 10)->default('SAR');
            $table->json('snapshot')->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();
            $table->index(['organization_id', 'invoice_date']);
            $table->index('archived_at');
        });

        Schema::create('audit_log_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('event', 50);
            $table->string('auditable_type', 255);
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log_archives');
        Schema::dropIfExists('invoice_archives');
        Schema::dropIfExists('journal_entry_archives');
    }
};
