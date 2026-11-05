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
        'reservations' => (int) env('ENGINE_RATE_LIMIT_RESERVATIONS', 20),
        'webhooks' => (int) env('ENGINE_RATE_LIMIT_WEBHOOKS', 120),
        // Le cancellazioni si fanno con un token: limite stretto contro i tentativi a caso.
        'cancellations' => (int) env('ENGINE_RATE_LIMIT_CANCELLATIONS', 10),
    ],

    // Durata delle Idempotency-Key.
    'idempotency_ttl_hours' => 24,

    // Disco delle ricevute: "local" in sviluppo, l'Object Storage su Laravel Cloud.
    'receipts_disk' => env('ENGINE_RECEIPTS_DISK', 'local'),

    // Pagina dell'interfaccia per annullare, linkata nell'email di conferma. {token} = token di cancellazione.
    'cancellation_url' => env('ENGINE_CANCELLATION_URL'),

    // Pagamenti online. "fake" simula PayPal (sviluppo e test) ed è vietato in produzione.
    'payments' => [
        'driver' => env('ENGINE_PAYMENTS_DRIVER', 'fake'),
        'paypal' => [
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        ],
    ],

    // Base del campo "type" dei problem+json: <base><code>.
    'problem_type_base' => env('ENGINE_PROBLEM_TYPE_BASE', 'https://docs.parcheggio.example/errors/'),

];
