<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // notifications table
        if (Schema::hasTable('notifications') && !$this->hasIndex('notifications', 'notifications_notifiable_read_at_index')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_at_index');
            });
        }

        // webhook_deliveries table
        if (Schema::hasTable('webhook_deliveries') && !$this->hasIndex('webhook_deliveries', 'webhook_deliveries_status_created_at_index')) {
            Schema::table('webhook_deliveries', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'webhook_deliveries_status_created_at_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_read_at_index');
        });
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropIndex('webhook_deliveries_status_created_at_index');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn($idx) => $idx['name'] === $indexName);
    }
};
