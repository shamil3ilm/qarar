<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // XBRL taxonomy definitions (IFRS, US-GAAP, local GCC/India standards)
        Schema::create('xbrl_taxonomies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('version', 20);
            $table->string('namespace')->unique();
            $table->string('schema_location')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        // XBRL filing submissions
        Schema::create('xbrl_filings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->foreignId('taxonomy_id')->constrained('xbrl_taxonomies');
            $table->enum('report_type', ['annual', 'semi_annual', 'quarterly', 'interim'])->default('annual');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'validated', 'submitted', 'accepted', 'rejected'])->default('draft');
            $table->longText('xml_content')->nullable();
            $table->json('validation_errors')->nullable();
            $table->string('external_reference')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'fiscal_year_id']);
        });

        // Individual XBRL-tagged elements within a filing
        Schema::create('xbrl_filing_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('xbrl_filing_id')->constrained('xbrl_filings')->cascadeOnDelete();
            $table->string('concept');          // e.g. ifrs-full:Equity
            $table->string('context_ref');      // e.g. duration_2023_2024
            $table->string('unit_ref')->nullable(); // e.g. SAR, ISO4217:USD
            $table->string('value', 1000);      // numeric or string value
            $table->integer('decimals')->nullable();
            $table->enum('period_type', ['instant', 'duration'])->default('instant');
            $table->enum('balance_type', ['debit', 'credit'])->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();

            $table->index('xbrl_filing_id');
            $table->index(['xbrl_filing_id', 'concept']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xbrl_filing_elements');
        Schema::dropIfExists('xbrl_filings');
        Schema::dropIfExists('xbrl_taxonomies');
    }
};
