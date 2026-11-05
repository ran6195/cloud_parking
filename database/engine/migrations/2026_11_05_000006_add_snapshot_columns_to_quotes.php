<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Il preventivo si rilegge "così com'è stato calcolato": durata, disponibilità
 * e termine di cancellazione gratuita vengono salvati insieme alle righe.
 */
return new class extends Migration
{
    protected $connection = 'engine';

    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->jsonb('stay')->comment('durata: minuti, giorni fatturati, giorni oltre il 30°');
            $table->jsonb('availability');
            $table->boolean('bookable');
            $table->timestampTz('cancellation_free_until');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['stay', 'availability', 'bookable', 'cancellation_free_until']);
        });
    }
};
