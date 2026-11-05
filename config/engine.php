<?php

/*
 * Configurazione tecnica del motore prenotazioni (App\Engine).
 * Le regole commerciali (posti, servizi, prezzi) stanno invece nelle
 * impostazioni del gestore: tabella engine.settings, classe EngineSettings.
 */
return [

    // Validità di un preventivo dalla sua creazione.
    'quote_ttl_minutes' => (int) env('ENGINE_QUOTE_TTL_MINUTES', 30),

    // Una sosta può iniziare al più tanti minuti nel passato (form compilato con calma).
    'start_tolerance_minutes' => (int) env('ENGINE_START_TOLERANCE_MINUTES', 15),

    // Richieste al minuto per client e IP. "default" vale se il client non ha un limite proprio.
    'rate_limits' => [
        'default' => (int) env('ENGINE_RATE_LIMIT', 120),
        'discounts' => (int) env('ENGINE_RATE_LIMIT_DISCOUNTS', 10),
    ],

    // Base del campo "type" dei problem+json: <base><code>.
    'problem_type_base' => env('ENGINE_PROBLEM_TYPE_BASE', 'https://docs.parcheggio.example/errors/'),

];
