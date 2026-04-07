<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outbound_message_id')->nullable()->constrained('outbound_messages')->nullOnDelete();
            $table->string('channel_type', 30); // email, sms, whatsapp, push_notification
            $table->string('provider', 50)->nullable();
            $table->string('recipient')->nullable(); // email address or phone number
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('pending'); // pending, sent, delivered, failed, bounced
            $table->string('provider_message_id')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'channel_type']);
            $table->index('outbound_message_id');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
