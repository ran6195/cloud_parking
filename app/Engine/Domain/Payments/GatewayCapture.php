<?php

namespace App\Engine\Domain\Payments;

use App\Engine\Domain\Shared\Money;

/**
 * Esito dell'incasso di un ordine.
 */
final readonly class GatewayCapture
{
    public const COMPLETED = 'COMPLETED';

    /** Il cliente non ha (ancora) approvato l'ordine. */
    public const NOT_APPROVED = 'NOT_APPROVED';

    /**
     * @param  string  $status  COMPLETED, PENDING, DECLINED, NOT_APPROVED…
     * @param  array<string, mixed>  $payload  risposta del provider
     */
    public function __construct(
        public string $orderId,
        public string $status,
        public ?string $captureId,
        public ?Money $amount,
        public ?string $currency,
        public array $payload = [],
    ) {}

    /**
     * Pagamento riuscito per l'importo atteso, in euro.
     */
    public function confirms(Money $expected): bool
    {
        return $this->status === self::COMPLETED
            && $this->currency === Money::CURRENCY
            && $this->amount !== null
            && $this->amount->equals($expected);
    }
}
