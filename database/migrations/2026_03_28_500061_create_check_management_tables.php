<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('check_register_entries');
        Schema::dropIfExists('check_books');

        Schema::create('check_books', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->name('cb_bank_account_fk');
            $table->string('check_book_number', 50);
            $table->string('from_check_number', 20);
            $table->string('to_check_number', 20);
            $table->string('current_check_number', 20);
            $table->enum('status', ['active', 'exhausted', 'cancelled'])->default('active');
            $table->date('issued_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'check_book_number'], 'cb_org_number_unq');
        });

        Schema::create('check_register_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('cre_org_fk');
            $table->foreignId('check_book_id')->nullable()->constrained('check_books')->name('cre_checkbook_fk');
            $table->string('check_number', 20);
            $table->enum('check_type', ['payment', 'payroll', 'refund', 'other'])->default('payment');
            $table->enum('direction', ['issued', 'received'])->default('issued');
            $table->foreignId('payee_id')->nullable()->constrained('contacts')->name('cre_payee_fk');
            $table->foreignId('payment_made_id')->nullable()->constrained('payment_mades')->name('cre_payment_made_fk');
            $table->foreignId('payment_received_id')->nullable()->constrained('payment_receiveds')->name('cre_payment_rcvd_fk');
            $table->date('check_date');
            $table->decimal('amount', 18, 4);
            $table->char('currency_code', 3)->default('SAR');
            $table->text('memo')->nullable();
            $table->enum('status', ['draft', 'printed', 'issued', 'presented', 'cleared', 'bounced', 'cancelled', 'stale'])
                ->default('draft');
            $table->dateTime('printed_at')->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('cleared_at')->nullable();
            $table->dateTime('bounced_at')->nullable();
            $table->text('bounce_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'check_number', 'direction'], 'cre_org_num_dir_unq');
            $table->index(['organization_id', 'status'], 'cre_org_status_idx');
            $table->index(['check_date'], 'cre_check_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_register_entries');
        Schema::dropIfExists('check_books');
    }
};
