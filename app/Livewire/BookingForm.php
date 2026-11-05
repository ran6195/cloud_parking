<?php

namespace App\Livewire;

use App\Services\Engine\EngineApiError;
use App\Services\Engine\EngineClient;
use App\Services\Engine\EngineUnavailable;
use App\Settings\GeneralSettings;
use App\Settings\ParkingSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Modulo di prenotazione della landing. Usa l'API del motore via HTTP (EngineClient),
 * come qualsiasi altra interfaccia: il prezzo lo calcola sempre il motore.
 *
 * Passi: date e servizi → preventivo → dati del cliente → prenotazione → pagamento.
 */
class BookingForm extends Component
{
    public const PEOPLE_OPTIONS = ['1' => '1', '2' => '2', '3' => '3', '4+' => '4 o più'];

    private const TIMEZONE = 'Europe/Rome';

    /** dates | details | done */
    public string $step = 'dates';

    public string $partenza = '';

    public string $rientro = '';

    public string $persone = '2';

    /** @var list<string> */
    public array $servizi = [];

    public string $pagamento = 'on_site';

    public string $codice = '';

    /** @var array<string, mixed>|null preventivo restituito dal motore */
    public ?array $quote = null;

    public string $nome = '';

    public string $cognome = '';

    public string $email = '';

    public string $telefono = '';

    public string $targa = '';

    public string $marca = '';

    public string $modello = '';

    public string $voloAndata = '';

    public string $voloRitorno = '';

    public string $note = '';

    public bool $privacy = false;

    public bool $marketing = false;

    /** @var array<string, mixed>|null prenotazione restituita dal motore */
    public ?array $reservation = null;

    /** Avviso per il cliente (prezzo cambiato, preventivo rinnovato, …). */
    public ?string $notice = null;

    /** Il motore non risponde: si propone la telefonata. */
    public bool $unavailable = false;

    public string $idempotencyKey = '';

    /** Codice di provenienza dalla pagina (?ref=…). */
    public ?string $referral = null;

    public function mount(): void
    {
        $ref = request()->query('ref');
        $this->referral = is_string($ref) && $ref !== '' ? mb_substr($ref, 0, 50) : null;
    }

    /** Corrispondenza tra i campi dell'API e quelli del modulo, per mostrare gli errori. */
    private const API_FIELDS = [
        'start' => 'partenza', 'end' => 'rientro', 'extras' => 'servizi', 'payment_method' => 'pagamento', 'discount_code' => 'codice',
        'customer.first_name' => 'nome', 'customer.last_name' => 'cognome', 'customer.email' => 'email', 'customer.phone' => 'telefono',
        'vehicle.plate' => 'targa', 'vehicle.make' => 'marca', 'vehicle.model' => 'modello',
        'travel.outbound_flight' => 'voloAndata', 'travel.return_flight' => 'voloRitorno', 'travel.passengers' => 'persone',
        'notes' => 'note', 'consents.privacy' => 'privacy',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'partenza' => ['required', 'date'],
            'rientro' => ['required', 'date', 'after:partenza'],
            'persone' => ['required', 'in:'.implode(',', array_keys(self::PEOPLE_OPTIONS))],
            'pagamento' => ['required', 'in:on_site,online'],
            'servizi' => ['array'],
            'codice' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailRules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:100'],
            'cognome' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['required', 'string', 'max:30'],
            'targa' => ['required', 'string', 'max:15'],
            'marca' => ['nullable', 'string', 'max:50'],
            'modello' => ['nullable', 'string', 'max:50'],
            'voloAndata' => ['nullable', 'string', 'max:20'],
            'voloRitorno' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:1000'],
            'privacy' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'partenza.required' => 'Indica quando lasci l\'auto.',
            'partenza.date' => 'La data di partenza non è valida.',
            'rientro.required' => 'Indica quando rientri.',
            'rientro.date' => 'La data di rientro non è valida.',
            'rientro.after' => 'Il rientro deve essere dopo la partenza.',
            'persone.required' => 'Indica quante persone viaggiano.',
            'persone.in' => 'Scegli un numero di persone tra quelli proposti.',
            'nome.required' => 'Indica il tuo nome.',
            'cognome.required' => 'Indica il tuo cognome.',
            'email.required' => 'Indica la tua email: ti mandiamo la conferma.',
            'email.email' => 'L\'indirizzo email non è valido.',
            'telefono.required' => 'Indica un numero di telefono.',
            'targa.required' => 'Indica la targa dell\'auto.',
            'privacy.accepted' => 'Per prenotare serve il consenso al trattamento dei dati.',
        ];
    }

    public function updated(string $property): void
    {
        // Il preventivo mostrato deve sempre corrispondere ai dati nel modulo.
        if (in_array($property, ['partenza', 'rientro', 'pagamento', 'codice'], true) || str_starts_with($property, 'servizi')) {
            $this->reset('quote', 'notice');
            $this->step = 'dates';
        }
    }

    public function calcola(EngineClient $engine): void
    {
        $this->validate();
        $this->notice = null;

        $this->call(function () use ($engine): void {
            $this->quote = $engine->quote($this->quoteRequest());
        });
    }

    public function avanti(): void
    {
        if (($this->quote['bookable'] ?? false) !== true) {
            return;
        }

        $this->step = 'details';
        $this->idempotencyKey = (string) Str::uuid();
    }

    public function indietro(): void
    {
        $this->step = 'dates';
    }

    public function prenota(EngineClient $engine): void
    {
        $this->validate($this->detailRules());
        $this->notice = null;

        try {
            $this->reservation = $engine->createReservation($this->reservationRequest(), $this->idempotencyKey);
            $this->step = 'done';
        } catch (EngineApiError $e) {
            $this->handleReservationError($e, $engine);
        } catch (EngineUnavailable) {
            $this->unavailable = true;
        }
    }

    /**
     * Pagamento simulato (sviluppo): crea l'ordine e lo incassa subito.
     */
    public function pagaSimulato(EngineClient $engine): void
    {
        $this->call(function () use ($engine): void {
            $order = $engine->createPaypalOrder($this->reservationId(), 'order-'.$this->reservationId());
            $this->reservation = $engine->capturePaypalOrder($this->reservationId(), (string) $order['order_id']);
        });
    }

    /**
     * Per i pulsanti PayPal: il server crea l'ordine con l'importo della prenotazione.
     */
    public function creaOrdinePaypal(EngineClient $engine): ?string
    {
        $orderId = null;
        $this->call(function () use ($engine, &$orderId): void {
            $orderId = (string) $engine->createPaypalOrder($this->reservationId(), 'order-'.$this->reservationId())['order_id'];
        });

        return $orderId;
    }

    public function incassaPaypal(EngineClient $engine, string $orderId): void
    {
        $this->call(function () use ($engine, $orderId): void {
            $this->reservation = $engine->capturePaypalOrder($this->reservationId(), $orderId);
        });
    }

    private function handleReservationError(EngineApiError $e, EngineClient $engine): void
    {
        match ($e->errorCode()) {
            'validation_failed' => $this->showFieldErrors($e),
            'quote_mismatch' => $this->replaceQuote($e, $e->getMessage()),
            'quote_expired' => $this->renewQuote($engine),
            'dates_unavailable' => $this->backToDates($e->getMessage()),
            default => $this->backToDates($e->getMessage()),
        };
    }

    private function replaceQuote(EngineApiError $e, string $message): void
    {
        /** @var array<string, mixed>|null $quote */
        $quote = $e->problem['quote'] ?? null;
        $this->quote = $quote;
        $this->notice = 'Il prezzo è cambiato: controlla il nuovo totale e conferma di nuovo.';
        $this->idempotencyKey = (string) Str::uuid();
        if ($quote === null) {
            $this->backToDates($message);
        }
    }

    /**
     * Il preventivo è scaduto mentre il cliente compilava: se ne chiede uno nuovo e si mostra il prezzo.
     */
    private function renewQuote(EngineClient $engine): void
    {
        $previous = $this->quote['total']['amount_cents'] ?? null;
        $this->call(function () use ($engine): void {
            $this->quote = $engine->quote($this->quoteRequest());
        });
        $this->idempotencyKey = (string) Str::uuid();
        $this->notice = ($this->quote['total']['amount_cents'] ?? null) === $previous
            ? 'Il preventivo era scaduto: l\'abbiamo rinnovato allo stesso prezzo. Conferma di nuovo.'
            : 'Il preventivo era scaduto e il prezzo è cambiato: controlla il nuovo totale e conferma di nuovo.';
    }

    private function backToDates(string $message): void
    {
        $this->step = 'dates';
        $this->quote = null;
        $this->notice = $message;
    }

    private function showFieldErrors(EngineApiError $e): void
    {
        foreach ($e->fieldErrors() as $field => $messages) {
            $this->addError(self::API_FIELDS[$field] ?? 'form', $messages[0] ?? 'Valore non valido.');
        }
    }

    /**
     * Esegue una chiamata al motore traducendo gli errori in messaggi del modulo.
     */
    private function call(callable $callback): void
    {
        try {
            $callback();
        } catch (EngineApiError $e) {
            $e->errorCode() === 'validation_failed' ? $this->showFieldErrors($e) : $this->addError('form', $e->getMessage());
        } catch (EngineUnavailable) {
            $this->unavailable = true;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteRequest(): array
    {
        return [
            'start' => Carbon::parse($this->partenza, self::TIMEZONE)->toIso8601String(),
            'end' => Carbon::parse($this->rientro, self::TIMEZONE)->toIso8601String(),
            'extras' => array_values($this->servizi),
            'payment_method' => $this->pagamento,
            'discount_code' => trim($this->codice) === '' ? null : trim($this->codice),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationRequest(): array
    {
        return [
            'quote_id' => $this->quote['id'] ?? null,
            'customer' => ['first_name' => $this->nome, 'last_name' => $this->cognome, 'email' => $this->email, 'phone' => $this->telefono],
            'vehicle' => ['plate' => $this->targa, 'make' => $this->blankToNull($this->marca), 'model' => $this->blankToNull($this->modello)],
            'travel' => [
                'outbound_flight' => $this->blankToNull($this->voloAndata),
                'return_flight' => $this->blankToNull($this->voloRitorno),
                'passengers' => $this->persone === '4+' ? 4 : (int) $this->persone,
            ],
            'billing' => null,
            'notes' => $this->blankToNull($this->note),
            'referral' => $this->referral,
            'consents' => ['privacy' => $this->privacy, 'marketing' => $this->marketing],
        ];
    }

    private function blankToNull(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }

    private function reservationId(): string
    {
        return (string) ($this->reservation['id'] ?? '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function availableExtras(EngineClient $engine): array
    {
        try {
            return array_values(array_filter($engine->extras(), fn (array $extra) => ($extra['pricing'] ?? null) !== 'informational'));
        } catch (EngineUnavailable|EngineApiError) {
            return [];
        }
    }

    public function render(EngineClient $engine): View
    {
        return view('livewire.booking-form', [
            'general' => app(GeneralSettings::class),
            'parking' => app(ParkingSettings::class),
            'extras' => $this->step === 'dates' && ! $this->unavailable ? $this->availableExtras($engine) : [],
            'minDate' => now(self::TIMEZONE)->format('Y-m-d\TH:i'),
            'paypalButtons' => (bool) config('services.engine.paypal_buttons') && filled(config('services.engine.paypal_client_id')),
            'paypalClientId' => config('services.engine.paypal_client_id'),
        ]);
    }
}
