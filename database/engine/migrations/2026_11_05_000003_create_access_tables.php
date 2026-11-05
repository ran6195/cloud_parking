<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accesso al motore: client API (interfacce), loro webhook, amministratori del pannello.
 */
return new class extends Migration
{
    protected $connection = 'engine';

    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('channel', 50)->comment('es. web, pwa, partner_x');
            $table->string('publishable_key', 64)->unique();
            $table->string('secret_key_hash', 64)->unique()->comment('sha256 della chiave sk_');
            $table->jsonb('allowed_origins')->default('[]');
            $table->jsonb('abilities')->default('[]');
            $table->unsignedSmallInteger('rate_limit_per_minute')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->text('secret')->comment('cifrato');
            $table->jsonb('events')->default('[]');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('api_clients');
    }
};
