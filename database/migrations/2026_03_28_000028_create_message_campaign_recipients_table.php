<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table already exists from an earlier migration
        if (Schema::hasTable('message_campaign_recipients')) {
            return;
        }

        Schema::create('message_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('messaging_automations')->nullOnDelete();
            $table->string('recipient_type', 30); // contact, user, custom
            $table->unsignedBigInteger('recipient_id')->nullable(); // polymorphic reference
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('name')->nullable();
            $table->string('status', 20)->default('pending'); // pending, sent, failed, unsubscribed
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['organization_id', 'recipient_type', 'recipient_id'], 'mcr_org_recipient_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_campaign_recipients');
    }
};
