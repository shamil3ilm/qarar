<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tds_sections', function (Blueprint $table) {
            $table->id();
            $table->string('section_code', 10)->unique();
            $table->string('description', 255);
            $table->decimal('threshold_amount', 15, 4)->default(0);
            $table->decimal('rate_individual', 5, 2)->default(0);
            $table->decimal('rate_company', 5, 2)->default(0);
            $table->decimal('rate_no_pan', 5, 2)->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active', 'tds_sections_active_idx');
        });

        Schema::create('tds_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->unique();
            $table->string('tan', 10)->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('deductor_name', 200);
            $table->string('deductor_type', 20);
            $table->string('responsible_person', 100)->nullable();
            $table->string('designation', 100)->nullable();
            $table->timestamps();

            $table->index('organization_id', 'tds_cfg_org_id_idx');
        });

        Schema::create('tds_deductions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->enum('deductee_type', ['vendor', 'employee', 'other']);
            $table->unsignedBigInteger('deductee_id');
            $table->unsignedBigInteger('section_id');
            $table->date('payment_date');
            $table->decimal('payment_amount', 15, 4);
            $table->decimal('tds_rate', 5, 2);
            $table->decimal('tds_amount', 15, 4);
            $table->decimal('surcharge', 15, 4)->default(0);
            $table->decimal('education_cess', 15, 4)->default(0);
            $table->decimal('net_tds', 15, 4);
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('challan_number', 30)->nullable();
            $table->timestamp('deposited_at')->nullable();
            $table->tinyInteger('period_quarter')->unsigned();
            $table->smallInteger('period_year')->unsigned();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'tds_ded_org_id_idx');
            $table->index(['organization_id', 'period_quarter', 'period_year'], 'tds_ded_org_period_idx');
            $table->index(['deductee_type', 'deductee_id'], 'tds_ded_deductee_idx');
            $table->index(['source_type', 'source_id'], 'tds_ded_source_idx');
            $table->index('section_id', 'tds_ded_section_id_idx');

            $table->foreign('section_id', 'tds_ded_section_fk')
                ->references('id')
                ->on('tds_sections')
                ->onDelete('restrict');
        });

        Schema::create('tds_certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('deductee_type', 20);
            $table->unsignedBigInteger('deductee_id');
            $table->tinyInteger('period_quarter')->unsigned();
            $table->smallInteger('period_year')->unsigned();
            $table->string('certificate_number', 30)->unique();
            $table->decimal('total_amount', 15, 4);
            $table->decimal('total_tds', 15, 4);
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'tds_cert_org_id_idx');
            $table->index(['organization_id', 'period_quarter', 'period_year'], 'tds_cert_org_period_idx');
            $table->index(['deductee_type', 'deductee_id'], 'tds_cert_deductee_idx');
        });

        Schema::create('tds_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->tinyInteger('quarter')->unsigned();
            $table->smallInteger('financial_year')->unsigned();
            $table->unsignedInteger('total_deductees')->default(0);
            $table->unsignedInteger('total_transactions')->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->decimal('total_tds', 15, 4)->default(0);
            $table->enum('status', ['draft', 'filed', 'revised'])->default('draft');
            $table->timestamp('filed_at')->nullable();
            $table->string('acknowledgement_number', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'tds_ret_org_id_idx');
            $table->unique(['organization_id', 'quarter', 'financial_year'], 'tds_ret_period_unq');
        });

        Schema::create('tcs_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('section_code', 10);
            $table->string('description', 200);
            $table->decimal('rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('organization_id', 'tcs_cfg_org_id_idx');
            $table->unique(['organization_id', 'section_code'], 'tcs_cfg_org_section_unq');
        });

        Schema::create('tcs_collections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->date('collection_date');
            $table->decimal('collection_amount', 15, 4);
            $table->decimal('tcs_rate', 5, 2);
            $table->decimal('tcs_amount', 15, 4);
            $table->boolean('deposited')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'tcs_col_org_id_idx');
            $table->index(['organization_id', 'collection_date'], 'tcs_col_org_date_idx');
            $table->index('contact_id', 'tcs_col_contact_id_idx');
            $table->index('invoice_id', 'tcs_col_invoice_id_idx');
        });

        // Seed default TDS sections
        $this->seedTdsSections();
    }

    public function down(): void
    {
        Schema::dropIfExists('tcs_collections');
        Schema::dropIfExists('tcs_configurations');
        Schema::dropIfExists('tds_returns');
        Schema::dropIfExists('tds_certificates');
        Schema::dropIfExists('tds_deductions');
        Schema::dropIfExists('tds_configurations');
        Schema::dropIfExists('tds_sections');
    }

    private function seedTdsSections(): void
    {
        $sections = [
            [
                'section_code'     => '194C',
                'description'      => 'Payment to Contractors and Sub-Contractors',
                'threshold_amount' => 30000.0000,
                'rate_individual'  => 1.00,
                'rate_company'     => 2.00,
                'rate_no_pan'      => 20.00,
                'is_active'        => true,
            ],
            [
                'section_code'     => '194I',
                'description'      => 'Rent',
                'threshold_amount' => 240000.0000,
                'rate_individual'  => 10.00,
                'rate_company'     => 10.00,
                'rate_no_pan'      => 20.00,
                'is_active'        => true,
            ],
            [
                'section_code'     => '194J',
                'description'      => 'Fees for Professional or Technical Services',
                'threshold_amount' => 30000.0000,
                'rate_individual'  => 10.00,
                'rate_company'     => 10.00,
                'rate_no_pan'      => 20.00,
                'is_active'        => true,
            ],
            [
                'section_code'     => '194H',
                'description'      => 'Commission or Brokerage',
                'threshold_amount' => 15000.0000,
                'rate_individual'  => 5.00,
                'rate_company'     => 5.00,
                'rate_no_pan'      => 20.00,
                'is_active'        => true,
            ],
            [
                'section_code'     => '194B',
                'description'      => 'Winnings from Lottery or Crossword Puzzle',
                'threshold_amount' => 10000.0000,
                'rate_individual'  => 30.00,
                'rate_company'     => 30.00,
                'rate_no_pan'      => 30.00,
                'is_active'        => true,
            ],
        ];

        \DB::table('tds_sections')->insert(
            array_map(function (array $row): array {
                $now = now()->toDateTimeString();
                return array_merge($row, ['created_at' => $now, 'updated_at' => $now]);
            }, $sections)
        );
    }
};
