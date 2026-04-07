<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sso_sessions');
        Schema::dropIfExists('sso_providers');

        Schema::create('sso_providers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('provider_name');
            $table->enum('protocol', ['oauth2', 'saml2', 'oidc']);
            $table->string('client_id')->nullable();
            $table->text('client_secret_encrypted')->nullable();
            $table->string('authorization_endpoint')->nullable();
            $table->string('token_endpoint')->nullable();
            $table->string('userinfo_endpoint')->nullable();
            $table->string('saml_entity_id')->nullable();
            $table->string('saml_sso_url')->nullable();
            $table->text('saml_certificate')->nullable();
            $table->json('attribute_mapping')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('sso_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('provider_id');
            $table->string('external_user_id');
            $table->string('access_token_hash')->nullable();
            $table->string('id_token_hash')->nullable();
            $table->timestamp('session_started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id', 'sso_sess_usr_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('provider_id', 'sso_sess_prov_fk')->references('id')->on('sso_providers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_sessions');
        Schema::dropIfExists('sso_providers');
    }
};
