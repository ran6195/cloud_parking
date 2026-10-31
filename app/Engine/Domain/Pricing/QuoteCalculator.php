<?php

namespace App\Engine\Domain\Pricing;

use App\Engine\Domain\Pricing\Discount\DiscountPolicy;
use App\Engine\Domain\Pricing\Exceptions\PriceListNotFound;
use App\Engine\Domain\Shared\Clock;
use App\Engine\Domain\Shared\Money;

/**
 * Calcola il preventivo: parcheggio dal listino, servizi, commissione di pagamento, sconto.
 * È l'unica fonte del prezzo per API, form e simulatore.
 */
final readonly class QuoteCalculator
{
    public function __construct(
        private TariffSource $tariffs,
        private ExtrasCatalog $extras,
        private PaymentFees $fees,
        private DiscountPolicy $discounts,
        private Clock $clock,
        private DurationPolicy $durationPolicy = new DurationPolicy,
    ) {}

    public function quote(QuoteRequest $request): Quote
    {
        $stay = $request->stay;
        $duration = $this->durationPolicy->measure($stay);
        $extras = $this->extras->resolve($request->extras);

        $match = $this->tariffs->lookup($stay->startDate())
            ?? throw PriceListNotFound::forDate($stay->startDate());

        $parking = $match->table->priceFor($duration);
        $hasCovered = array_filter($extras, fn (ExtraDefinition $e) => $e->pricing === ExtraPricing::PercentageOfParking) !== [];

        $lines = [new QuoteLine(
            QuoteLineType::Parking,
            'parking',
            $hasCovered ? 'Parcheggio' : 'Parcheggio scoperto',
            $duration->billableDays,
            null,
            $parking,
        )];

        foreach ($extras as $extra) {
            $lines[] = $this->extraLine($extra, $parking, $duration);
        }

        $fee = $this->fees->for($request->paymentMethod);
        if ($fee !== null) {
            $lines[] = new QuoteLine(
                QuoteLineType::PaymentFee,
                $request->paymentMethod->value,
                $this->fees->onSiteDescription,
                1,
                $fee,
                $fee,
            );
        }

        $subtotal = Money::sum(array_map(fn (QuoteLine $line) => $line->total, $lines));

        $discount = $request->discountCode === null || trim($request->discountCode) === ''
            ? null
            : $this->discounts->apply($request->discountCode, $subtotal, $this->clock->now());

        $total = $discount?->amount === null ? $subtotal : $subtotal->minus($discount->amount);

        return new Quote(
            stay: $stay,
            duration: $duration,
            extras: array_map(fn (ExtraDefinition $e) => $e->id, $extras),
            paymentMethod: $request->paymentMethod,
            lines: $lines,
            subtotal: $subtotal,
            discount: $discount,
            total: $total,
            trace: new PricingTrace(
                priceTable: $match->table->label,
                requestedDate: $match->requestedDate,
                matchedDate: $match->matchedDate,
                fallbackYears: $match->yearsBack,
            ),
        );
    }

    private function extraLine(ExtraDefinition $extra, Money $parking, StayDuration $duration): QuoteLine
    {
        return match ($extra->pricing) {
            ExtraPricing::PercentageOfParking => new QuoteLine(
                QuoteLineType::Extra,
                $extra->id->value,
                $extra->lineDescription(),
                $duration->billableDays,
                null,
                $parking->percentage((int) $extra->percentage),
            ),
            ExtraPricing::Fixed => new QuoteLine(
                QuoteLineType::Extra,
                $extra->id->value,
                $extra->lineDescription(),
                1,
                $extra->price,
                $extra->price ?? Money::zero(),
            ),
            ExtraPricing::Informational => new QuoteLine(
                QuoteLineType::Info,
                $extra->id->value,
                $extra->lineDescription(),
                1,
                Money::zero(),
                Money::zero(),
            ),
        };
    }
}
