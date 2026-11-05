<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - reservations.discount: esito del codice sconto copiato dal preventivo (codice, tipo, percentuale)
 * - idempotency_keys: corpo della risposta come testo (si ripete identico, jsonb riordinerebbe le chiavi)
 *   e header da ripetere (es. Location)
 */
return new class extends Migration
{
    protected $connection = 'engine';

    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->jsonb('discount')->nullable();
        });

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->text('response_body')->nullable()->change();
            $table->jsonb('response_headers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropColumn('response_headers');
        });
        DB::connection($this->connection)->statement('alter table idempotency_keys alter column response_body type jsonb using response_body::jsonb');
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn('discount'));
    }
};
