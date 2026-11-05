<?php

namespace App\Engine\Console;

use App\Engine\Models\ApiClient;
use Illuminate\Console\Command;

/**
 * Registra un'interfaccia che userà l'API e ne stampa le chiavi.
 * La chiave segreta compare solo qui: va copiata subito (es. in ENGINE_API_KEY).
 */
class CreateApiClientCommand extends Command
{
    protected $signature = 'engine:client
        {name : Nome leggibile, es. "Form del CMS"}
        {channel : Canale registrato sulle prenotazioni, es. web, pwa, partner_x}
        {--origin=* : Origine consentita per la chiave pk_ (ripetibile), es. https://sostablq.it}
        {--rate-limit= : Richieste al minuto (default da config engine.rate_limits.default)}';

    protected $description = 'Crea un client API del motore con le sue chiavi pk_ e sk_';

    public function handle(): int
    {
        $channel = (string) $this->argument('channel');
        if (preg_match('/^[a-z0-9_]{1,50}$/', $channel) !== 1) {
            $this->components->error('Il canale può contenere solo lettere minuscole, cifre e "_" (al massimo 50).');

            return self::FAILURE;
        }

        /** @var list<string> $origins */
        $origins = (array) $this->option('origin');
        foreach ($origins as $origin) {
            if (preg_match('#^https?://[^/\s]+$#i', rtrim($origin, '/')) !== 1) {
                $this->components->error("Origine non valida: [{$origin}]. Esempio: https://sostablq.it");

                return self::FAILURE;
            }
        }

        $rateLimit = $this->option('rate-limit');
        ['client' => $client, 'secret_key' => $secret] = ApiClient::issue(
            (string) $this->argument('name'),
            $channel,
            $origins,
            $rateLimit === null ? null : max(1, (int) $rateLimit),
        );

        $this->components->info("Client #{$client->id} \"{$client->name}\" creato sul canale {$client->channel}.");
        $this->components->twoColumnDetail('Chiave pubblicabile (browser)', $client->publishable_key);
        $this->components->twoColumnDetail('Chiave segreta (server)', $secret);
        $this->components->twoColumnDetail('Origini consentite', $client->allowed_origins === [] ? '—' : implode(', ', $client->allowed_origins));
        $this->components->warn('La chiave segreta non verrà più mostrata: copiala ora.');

        return self::SUCCESS;
    }
}
