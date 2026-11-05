<?php

namespace App\Engine\Application;

use App\Engine\Domain\Availability\AvailabilityChecker;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\QuoteCalculator;
use App\Engine\Domain\Pricing\QuoteRequest;
use App\Engine\Domain\Shared\Clock;
use App\Engine\Models\ApiClient;
use App\Engine\Models\Discount;
use App\Engine\Models\Quote;
use App\Engine\Settings\EngineSettings;
use Carbon\CarbonImmutable;

/**
 * Calcola un preventivo, ne verifica la disponibilità e lo salva con la sua scadenza.
 * Un periodo non disponibile non è un errore: il preventivo esce con bookable = false.
 */
final readonly class QuoteService
{
    public function __construct(
        private QuoteCalculator $calculator,
        private AvailabilityChecker $availability,
        private EngineSettings $settings,
        private Clock $clock,
    ) {}

    public function create(QuoteRequest $request, ApiClient $client): Quote
    {
        $quote = $this->calculator->quote($request);
        $availability = $this->availability->check($request->stay, $quote->extras);
        $now = CarbonImmutable::instance($this->clock->now());

        $discountId = $quote->discount?->applied === true
            ? Discount::query()->where('code', $quote->discount->code)->value('id')
            : null;

        return Quote::query()->create([
            'api_client_id' => $client->id,
            'discount_id' => $discountId,
            'starts_at' => $quote->stay->start,
            'ends_at' => $quote->stay->end,
            'extras' => array_map(fn (ExtraId $id) => $id->value, $quote->extras),
            'payment_method' => $quote->paymentMethod,
            'stay' => QuoteSnapshot::stay($quote),
            'lines' => QuoteSnapshot::lines($quote),
            'subtotal_cents' => $quote->subtotal->cents,
            'discount_cents' => $quote->subtotal->cents - $quote->total->cents,
            'total_cents' => $quote->total->cents,
            'discount' => QuoteSnapshot::discount($quote->discount),
            'trace' => QuoteSnapshot::trace($quote),
            'availability' => QuoteSnapshot::availability($availability),
            'bookable' => $availability->isAvailable(),
            'cancellation_free_until' => CarbonImmutable::instance($quote->stay->start)->subHours($this->settings->cancellationHours()),
            'expires_at' => $now->addMinutes((int) config('engine.quote_ttl_minutes')),
        ]);
    }
}
