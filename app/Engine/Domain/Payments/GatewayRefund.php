<?php

namespace App\Engine\Domain\Payments;

final readonly class GatewayRefund
{
    /**
     * @param  string  $status  COMPLETED, PENDING, FAILED…
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ?string $id,
        public string $status,
        public array $payload = [],
    ) {}

    public function succeeded(): bool
    {
        return in_array($this->status, ['COMPLETED', 'PENDING'], true);
    }
}
