<?php

namespace App\Engine\Domain\Payments;

use App\Engine\Domain\Shared\Money;

final readonly class GatewayOrder
{
    /**
     * @param  string  $status  CREATED (da approvare), APPROVED, COMPLETED
     * @param  array<string, mixed>  $payload  risposta del provider
     */
    public function __construct(
        public string $id,
        public string $status,
        public Money $amount,
        public array $payload = [],
    ) {}
}
