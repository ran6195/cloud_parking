<?php

namespace App\Engine\Domain\Pricing;

use App\Engine\Domain\Shared\Money;

/**
 * Commissioni legate al metodo di pagamento (legacy: "in_aeroporto", 1 €).
 */
final readonly class PaymentFees
{
    public function __construct(
        public Money $onSite,
        public string $onSiteDescription = 'Pagamento in parcheggio',
    ) {}

    public function for(PaymentMethod $method): ?Money
    {
        return match ($method) {
            PaymentMethod::OnSite => $this->onSite->isZero() ? null : $this->onSite,
            PaymentMethod::Online => null,
        };
    }
}
