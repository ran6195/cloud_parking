<?php

namespace App\Engine\Application;

/**
 * Dati della prenotazione già validati (schema ReservationRequest della spec).
 */
final readonly class ReservationInput
{
    /**
     * @param  array{first_name: string, last_name: string, email: string, phone: string}  $customer
     * @param  array{plate: string, make?: string|null, model?: string|null}  $vehicle
     * @param  array{outbound_flight?: string|null, return_flight?: string|null, passengers: int}  $travel
     * @param  array<string, mixed>|null  $billing
     * @param  array{privacy: bool, marketing: bool}  $consents
     */
    public function __construct(
        public string $quoteId,
        public array $customer,
        public array $vehicle,
        public array $travel,
        public ?array $billing,
        public ?string $notes,
        public ?string $referral,
        public array $consents,
    ) {}
}
