<?php

namespace App\Engine\Domain\Pricing\Discount;

use App\Engine\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Regola di sconto configurata dal gestore.
 *
 * - validUntil: ultimo giorno di validità (incluso); null = senza scadenza
 * - maxUses: utilizzi consentiti; null = illimitati
 * - timesUsed: utilizzi già effettuati (prenotazioni confermate o pagate)
 */
final readonly class Discount
{
    public function __construct(
        public DiscountCode $code,
        public DiscountType $type,
        public ?int $percentage = null,
        public ?Money $amount = null,
        public ?DateTimeImmutable $validUntil = null,
        public ?int $maxUses = null,
        public int $timesUsed = 0,
    ) {
        if ($type === DiscountType::Percentage && ($percentage === null || $percentage < 1 || $percentage > 100)) {
            throw new InvalidArgumentException("Sconto {$code->value}: percentuale tra 1 e 100 obbligatoria.");
        }

        if ($type === DiscountType::FixedAmount && ($amount === null || $amount->isNegative() || $amount->isZero())) {
            throw new InvalidArgumentException("Sconto {$code->value}: importo positivo obbligatorio.");
        }

        if ($maxUses !== null && $maxUses < 0) {
            throw new InvalidArgumentException("Sconto {$code->value}: numero massimo di utilizzi non valido.");
        }
    }

    public static function percentage(string $code, int $percentage): self
    {
        return new self(new DiscountCode($code), DiscountType::Percentage, percentage: $percentage);
    }

    public static function fixed(string $code, int $cents): self
    {
        return new self(new DiscountCode($code), DiscountType::FixedAmount, amount: Money::cents($cents));
    }

    /**
     * Motivo per cui lo sconto non è utilizzabile alla data indicata, oppure null se è valido.
     */
    public function rejectionAt(DateTimeImmutable $today): ?DiscountRejection
    {
        if ($this->validUntil !== null && $today->format('Y-m-d') > $this->validUntil->format('Y-m-d')) {
            return DiscountRejection::Expired;
        }

        if ($this->maxUses !== null && $this->timesUsed >= $this->maxUses) {
            return DiscountRejection::Exhausted;
        }

        return null;
    }
}
