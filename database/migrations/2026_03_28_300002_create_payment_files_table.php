<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payment_files');

        Schema::create('payment_files', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('payment_run_id')->nullable();
            $table->string('file_format', 20);
            $table->string('file_name');
            $table->longText('file_content');
            $table->string('message_id', 50);
            $table->dateTime('creation_datetime');
            $table->integer('number_of_transactions')->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->string('currency_code', 3);
            $table->string('status', 20)->default('generated');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'fk_pf_org')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('payment_run_id', 'fk_pf_payment_run')
                ->references('id')->on('payment_runs')->onDelete('set null');
            $table->foreign('created_by', 'fk_pf_created_by')
                ->references('id')->on('users')->onDelete('set null');

            $table->index(['organization_id', 'status'], 'idx_pf_org_status');
            $table->index('payment_run_id', 'idx_pf_run');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_files');
    }
};
