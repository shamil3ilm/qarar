<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make notifications.title and message nullable so Laravel's database notification
 * channel (which only provides id, type, data, read_at) can insert records without
 * violating NOT NULL constraints. Title/message are populated explicitly when creating
 * in-app notifications directly; they remain null for channel-dispatched notifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
            $table->text('message')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
            $table->text('message')->nullable(false)->change();
        });
    }
};
