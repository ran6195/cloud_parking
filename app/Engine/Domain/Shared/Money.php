<?php

namespace App\Engine\Domain\Shared;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Importo in centesimi interi. Niente float per i soldi.
 */
final readonly class Money implements JsonSerializable
{
    public const CURRENCY = 'EUR';

    private function __construct(public int $cents) {}

    public static function cents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Converte un importo decimale ("12.50", 12.5) in centesimi, arrotondando al centesimo.
     * Da usare solo ai confini (import, impostazioni), mai nei calcoli.
     */
    public static function fromDecimal(string|int|float $amount): self
    {
        if (is_string($amount) && ! is_numeric($amount)) {
            throw new InvalidArgumentException("Importo non valido: [{$amount}]");
        }

        return new self((int) round(((float) $amount) * 100));
    }

    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function minus(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function times(int $multiplier): self
    {
        return new self($this->cents * $multiplier);
    }

    /**
     * Percentuale dell'importo, arrotondata al centesimo (metà per eccesso).
     */
    public function percentage(int $percent): self
    {
        if ($percent < 0) {
            throw new InvalidArgumentException('La percentuale non può essere negativa.');
        }

        return new self(self::divideRoundingHalfUp($this->cents * $percent, 100));
    }

    public function min(self $other): self
    {
        return $this->cents <= $other->cents ? $this : $other;
    }

    public function max(self $other): self
    {
        return $this->cents >= $other->cents ? $this : $other;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    /**
     * @param  iterable<self>  $amounts
     */
    public static function sum(iterable $amounts): self
    {
        $total = 0;
        foreach ($amounts as $amount) {
            $total += $amount->cents;
        }

        return new self($total);
    }

    /**
     * "12.50": per log, export e confronti con il legacy.
     */
    public function toDecimalString(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $abs = abs($this->cents);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }

    /**
     * @return array{amount_cents: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return ['amount_cents' => $this->cents, 'currency' => self::CURRENCY];
    }

    private static function divideRoundingHalfUp(int $numerator, int $denominator): int
    {
        $sign = $numerator < 0 ? -1 : 1;

        return $sign * intdiv(abs($numerator) * 2 + $denominator, $denominator * 2);
    }
}
