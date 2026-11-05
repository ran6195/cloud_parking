<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Una prenotazione in attesa del pagamento online occupa il posto fino al
 * termine di pagamento: il termine è quindi obbligatorio.
 */
return new class extends Migration
{
    protected $connection = 'engine';

    public function up(): void
    {
        DB::connection($this->connection)->statement(<<<'SQL'
            alter table reservations add constraint reservations_pending_due_by_check
                check (status <> 'pending_payment' or payment_due_by is not null)
            SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->statement('alter table reservations drop constraint reservations_pending_due_by_check');
    }
};
