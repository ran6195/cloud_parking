<?php

namespace App\Engine\Application;

use App\Engine\Application\Exceptions\DatesUnavailable;
use App\Engine\Application\Exceptions\QuoteMismatch;
use App\Engine\Application\Exceptions\QuoteNotUsable;
use App\Engine\Domain\Availability\Availability;
use App\Engine\Domain\Availability\AvailabilityChecker;
use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\PaymentMethod;
use App\Engine\Domain\Pricing\Quote as PricedQuote;
use App\Engine\Domain\Pricing\QuoteCalculator;
use App\Engine\Domain\Pricing\QuoteRequest;
use App\Engine\Domain\Pricing\StayPeriod;
use App\Engine\Domain\Shared\Clock;
use App\Engine\EngineServiceProvider;
use App\Engine\Models\ApiClient;
use App\Engine\Models\Customer;
use App\Engine\Models\Quote;
use App\Engine\Models\Reservation;
use App\Engine\Models\ReservationLine;
use App\Engine\Settings\EngineSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Converte un preventivo in prenotazione.
 *
 * Dentro una transazione con un lock esclusivo (pg_advisory_xact_lock) ricalcola
 * prezzo e disponibilità: due richieste contemporanee per l'ultimo posto non
 * possono passare entrambe. Il lock è a livello di transazione, quindi funziona
 * anche con il connection pooler di Laravel Cloud.
 */
final readonly class ReservationService
{
    /** Chiave del lock condiviso da tutte le creazioni di prenotazione. */
    public const LOCK_KEY = 7_340_001;

    public function __construct(
        private QuoteCalculator $calculator,
        private AvailabilityChecker $availability,
        private QuoteService $quotes,
        private ReservationNotifier $notifier,
        private EngineSettings $settings,
        private Clock $clock,
    ) {}

    public function create(ReservationInput $input, ApiClient $client): Reservation
    {
        // Un preventivo di un altro client è come inesistente (404).
        Quote::query()->whereKey($input->quoteId)->where('api_client_id', $client->id)->firstOrFail();

        try {
            $reservation = DB::connection(EngineServiceProvider::CONNECTION)->transaction(
                fn () => $this->createLocked($input, $client),
            );
        } catch (PriceChanged $changed) {
            throw new QuoteMismatch($changed->getMessage(), $this->quotes->create($changed->request, $client));
        }

        if (in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::Paid], true)) {
            $this->notifier->confirmed($reservation);
        }

        return $reservation->refresh();
    }

    private function createLocked(ReservationInput $input, ApiClient $client): Reservation
    {
        DB::connection(EngineServiceProvider::CONNECTION)->select('select pg_advisory_xact_lock(?)', [self::LOCK_KEY]);

        $quote = Quote::query()->whereKey($input->quoteId)->lockForUpdate()->firstOrFail();
        $now = CarbonImmutable::instance($this->clock->now());

        if ($quote->used_at !== null) {
            throw QuoteNotUsable::used();
        }
        if ($quote->isExpired($now)) {
            throw QuoteNotUsable::expired();
        }

        $request = self::requestFrom($quote);
        $priced = $this->calculator->quote($request);
        $this->ensureSamePrice($quote, $priced, $request);

        $availability = $this->availability->check($request->stay, $priced->extras);
        if (! $availability->isAvailable()) {
            throw new DatesUnavailable(
                "Le date selezionate non sono più disponibili. Contattaci al {$this->settings->contactPhone()} per verificare la disponibilità residua.",
                self::unavailableDates($availability),
            );
        }

        // Online con totale 0 (sconto del 100%): non c'è nulla da incassare, è già pagata.
        $online = $priced->paymentMethod === PaymentMethod::Online;
        $free = $online && $priced->total->isZero();
        $reservation = Reservation::query()->create([
            'customer_id' => $this->customer($input)->id,
            'api_client_id' => $client->id,
            'quote_id' => $quote->id,
            'discount_id' => $quote->discount_id,
            'channel' => $client->channel,
            'status' => match (true) {
                $free => ReservationStatus::Paid,
                $online => ReservationStatus::PendingPayment,
                default => ReservationStatus::Confirmed,
            },
            'paid_at' => $free ? $now : null,
            'starts_at' => $quote->starts_at,
            'ends_at' => $quote->ends_at,
            'billable_days' => $priced->duration->billableDays,
            'plate' => $input->vehicle['plate'],
            'vehicle_make' => $input->vehicle['make'] ?? null,
            'vehicle_model' => $input->vehicle['model'] ?? null,
            'outbound_flight' => $input->travel['outbound_flight'] ?? null,
            'return_flight' => $input->travel['return_flight'] ?? null,
            'passengers' => $input->travel['passengers'],
            'notes' => $input->notes,
            'referral' => $input->referral,
            'billing' => $input->billing,
            'consents' => $input->consents,
            'payment_method' => $priced->paymentMethod,
            'subtotal_cents' => $priced->subtotal->cents,
            'total_cents' => $priced->total->cents,
            'discount' => QuoteSnapshot::discount($priced->discount),
            'payment_due_by' => $online && ! $free ? $now->addMinutes($this->settings->onlinePaymentWindowMinutes()) : null,
        ]);

        foreach ($priced->lines as $i => $line) {
            $reservation->lines()->create(ReservationLine::attributesFrom($line, $i + 1));
        }

        $quote->forceFill(['used_at' => $now])->save();

        return $reservation;
    }

    private static function requestFrom(Quote $quote): QuoteRequest
    {
        $code = $quote->discount['code'] ?? null;

        return new QuoteRequest(
            stay: new StayPeriod($quote->starts_at->toDateTimeImmutable(), $quote->ends_at->toDateTimeImmutable()),
            extras: array_map(ExtraId::from(...), $quote->extras),
            paymentMethod: $quote->payment_method,
            discountCode: is_string($code) ? $code : null,
        );
    }

    /**
     * Il cliente deve vedere lo stesso prezzo del preventivo: righe, totale e sconto.
     */
    private function ensureSamePrice(Quote $quote, PricedQuote $priced, QuoteRequest $request): void
    {
        if (QuoteSnapshot::lines($priced) == $quote->lines && $priced->total->cents === $quote->total_cents) {
            return;
        }

        $discountLost = ($quote->discount['applied'] ?? false) === true && $priced->discount?->applied !== true;

        throw new PriceChanged(
            $discountLost
                ? 'Il codice sconto non è più valido. Il nuovo preventivo è nel campo quote.'
                : 'Il prezzo è cambiato. Il nuovo preventivo è nel campo quote.',
            $request,
        );
    }

    /**
     * Un cliente per email: i dati di contatto si aggiornano con quelli dell'ultima prenotazione.
     */
    private function customer(ReservationInput $input): Customer
    {
        $customer = Customer::query()
            ->where('email_normalized', Customer::normalizeEmail($input->customer['email']))
            ->first() ?? new Customer;

        $customer->fill($input->customer)->save();

        return $customer;
    }

    /**
     * @return list<string>
     */
    private static function unavailableDates(Availability $availability): array
    {
        $dates = $availability->parking->unavailableDates;
        foreach ($availability->extras as $extra) {
            $dates = [...$dates, ...$extra->unavailableDates];
        }
        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }
}
