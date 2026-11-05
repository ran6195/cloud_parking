<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Listini: un periodo (estremi inclusi) con il prezzo totale per 1..30 giorni
 * e il prezzo di ogni giorno oltre il 30°. I periodi dei listini attivi non
 * possono sovrapporsi (vincolo di esclusione Postgres).
 */
return new class extends Migration
{
    protected $connection = 'engine';

    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedInteger('extra_day_cents');
            $table->string('source', 20)->default('manual');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::connection($this->connection)->statement(<<<'SQL'
            alter table price_lists
                add constraint price_lists_period_check check (ends_on >= starts_on),
                add constraint price_lists_extra_day_check check (extra_day_cents >= 0),
                add constraint price_lists_source_check check (source in ('manual', 'legacy_exceptions')),
                add constraint price_lists_no_overlap
                    exclude using gist (daterange(starts_on, ends_on, '[]') with &&)
                    where (deleted_at is null)
            SQL);

        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days');
            $table->unsignedInteger('amount_cents');
            $table->timestamps();

            $table->unique(['price_list_id', 'days']);
        });

        DB::connection($this->connection)->statement(<<<'SQL'
            alter table prices
                add constraint prices_days_check check (days between 1 and 30),
                add constraint prices_amount_check check (amount_cents >= 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
        Schema::dropIfExists('price_lists');
    }
};
