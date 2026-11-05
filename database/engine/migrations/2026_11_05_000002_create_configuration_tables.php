<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurazione del gestore: impostazioni, date bloccate, codici sconto.
 */
return new class extends Migration
{
    protected $connection = 'engine';

    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->jsonb('value');
            $table->timestamps();
        });

        Schema::create('blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('type', 20);
            $table->unsignedSmallInteger('percentage')->nullable();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->unsignedInteger('max_uses')->nullable()->comment('null = illimitati');
            $table->date('valid_until')->nullable()->comment('ultimo giorno valido, incluso');
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::connection($this->connection)->statement(<<<'SQL'
            alter table discounts
                add constraint discounts_code_normalized_check check (code = upper(btrim(code)) and code <> ''),
                add constraint discounts_type_check check (
                    (type = 'percentage' and percentage between 1 and 100 and amount_cents is null)
                    or (type = 'fixed_amount' and amount_cents > 0 and percentage is null)
                )
            SQL);
        DB::connection($this->connection)->statement(
            'create unique index discounts_code_active_unique on discounts (code) where deleted_at is null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('blocked_dates');
        Schema::dropIfExists('settings');
    }
};
