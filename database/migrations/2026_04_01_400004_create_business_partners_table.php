<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business Partner (BP) / CVI — SAP customer-vendor integration.
 *
 * A Business Partner is the single master record that can play the role
 * of Customer, Vendor (Supplier), or both.  This replaces the separate
 * customer/contact and supplier records and links them to one BP.
 *
 * Roles (bp_roles):
 *   FLCU00  General customer
 *   FLVN00  General vendor / supplier
 *   BUP001  Person
 *   BUP002  Organisation
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_partners', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');

            $table->string('bp_number', 30)->unique();         // auto-generated BP-XXXXX
            $table->string('bp_category', 10)->default('ORG'); // ORG | PERSON
            $table->string('name');
            $table->string('name2')->nullable();               // legal name / trade name
            $table->string('search_term', 100)->nullable();

            // Contact info
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('website')->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->string('commercial_reg', 50)->nullable();

            // Address
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->nullable();          // ISO alpha-2

            // Linked CRM contact and Purchase supplier
            $table->unsignedBigInteger('contact_id')->nullable(); // -> contacts
            $table->unsignedBigInteger('supplier_id')->nullable(); // -> suppliers

            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'bp_number']);
            $table->index(['organization_id', 'contact_id']);
            $table->index(['organization_id', 'supplier_id']);
        });

        Schema::create('business_partner_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_partner_id');
            $table->string('role_code', 20);                   // FLCU00 | FLVN00 | BUP001 | BUP002
            $table->string('role_name');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_partner_id', 'role_code']);
            $table->foreign('business_partner_id')->references('id')->on('business_partners')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_partner_roles');
        Schema::dropIfExists('business_partners');
    }
};
