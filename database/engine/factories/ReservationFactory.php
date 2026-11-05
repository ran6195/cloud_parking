<?php

namespace Database\Engine\Factories;

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Pricing\PaymentMethod;
use App\Engine\Models\Customer;
use App\Engine\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Prenotazione confermata di 3 giorni a 25 €, da pagare in parcheggio.
 *
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'channel' => 'web',
            'status' => ReservationStatus::Confirmed,
            'starts_at' => CarbonImmutable::parse('2026-11-20 08:00', 'Europe/Rome'),
            'ends_at' => CarbonImmutable::parse('2026-11-23 08:00', 'Europe/Rome'),
            'billable_days' => 3,
            'plate' => strtoupper(fake()->bothify('??###??')),
            'passengers' => 2,
            'consents' => ['privacy' => true, 'marketing' => false],
            'payment_method' => PaymentMethod::OnSite,
            'subtotal_cents' => 2500,
            'total_cents' => 2500,
        ];
    }

    /**
     * In attesa del pagamento, il termine è obbligatorio: di default fra 30 minuti.
     */
    public function status(ReservationStatus $status, ?CarbonImmutable $paymentDueBy = null): static
    {
        return $this->state([
            'status' => $status,
            'payment_due_by' => $status === ReservationStatus::PendingPayment
                ? ($paymentDueBy ?? CarbonImmutable::now()->addMinutes(30))
                : $paymentDueBy,
        ]);
    }

    /**
     * Aggiunge alla prenotazione la riga di un servizio (es. covered_parking, key_drop).
     */
    public function withExtra(string $code): static
    {
        return $this->afterCreating(function (Reservation $reservation) use ($code): void {
            $reservation->lines()->create([
                'position' => (int) $reservation->lines()->max('position') + 1,
                'type' => 'extra',
                'code' => $code,
                'description' => $code,
                'quantity' => 1,
                'unit_cents' => 0,
                'total_cents' => 0,
            ]);
        });
    }

    public function between(string $start, string $end): static
    {
        return $this->state([
            'starts_at' => CarbonImmutable::parse($start, 'Europe/Rome'),
            'ends_at' => CarbonImmutable::parse($end, 'Europe/Rome'),
        ]);
    }
}
