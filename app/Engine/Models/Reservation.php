<?php

namespace App\Engine\Models;

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Pricing\PaymentMethod;
use Carbon\CarbonImmutable;
use Database\Engine\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Prenotazione. start_date e end_date sono le date locali (Europe/Rome) di
 * starts_at ed ends_at, ricavate al salvataggio: servono alle query di disponibilità.
 * "number" è il numero leggibile, che prosegue la numerazione legacy.
 *
 * @property string $id
 * @property int $number
 * @property int $customer_id
 * @property int|null $api_client_id
 * @property string|null $quote_id
 * @property int|null $discount_id
 * @property string $channel
 * @property ReservationStatus $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property int $billable_days
 * @property string $plate
 * @property string|null $vehicle_make
 * @property string|null $vehicle_model
 * @property string|null $outbound_flight
 * @property string|null $return_flight
 * @property int $passengers
 * @property string|null $notes
 * @property string|null $referral
 * @property array<string, mixed>|null $billing
 * @property array<string, bool> $consents
 * @property PaymentMethod $payment_method
 * @property int $subtotal_cents
 * @property int $discount_cents
 * @property int $total_cents
 * @property CarbonImmutable|null $payment_due_by
 * @property CarbonImmutable|null $paid_at
 * @property string|null $receipt_path
 * @property bool $email_sent
 * @property string $cancellation_token
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property array<string, mixed>|null $refund
 * @property array<string, mixed>|null $discount esito del codice sconto, copiato dal preventivo
 * @property CarbonImmutable $created_at
 */
#[UseFactory(ReservationFactory::class)]
class Reservation extends EngineModel
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = ['id', 'number', 'start_date', 'end_date', 'discount_cents'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => ReservationStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'billable_days' => 'integer',
            'passengers' => 'integer',
            'billing' => 'array',
            'consents' => 'array',
            'payment_method' => PaymentMethod::class,
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'payment_due_by' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'email_sent' => 'boolean',
            'cancelled_at' => 'immutable_datetime',
            'refund' => 'array',
            'discount' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation): void {
            $reservation->cancellation_token ??= bin2hex(random_bytes(32));
        });

        static::saving(function (Reservation $reservation): void {
            $reservation->plate = self::normalizePlate($reservation->plate);
            $reservation->setAttribute('start_date', self::localDate($reservation->starts_at));
            $reservation->setAttribute('end_date', self::localDate($reservation->ends_at));
            $reservation->setAttribute('discount_cents', $reservation->subtotal_cents - $reservation->total_cents);
        });
    }

    /**
     * Cambia stato rispettando le transizioni consentite (ReservationStatus).
     */
    public function transitionTo(ReservationStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new LogicException("Transizione non consentita: {$this->status->value} → {$next->value}.");
        }

        $this->status = $next;
        $this->save();
    }

    public static function normalizePlate(string $plate): string
    {
        return mb_strtoupper((string) preg_replace('/[\s\-]+/', '', $plate));
    }

    private static function localDate(CarbonImmutable $instant): string
    {
        return $instant->setTimezone(self::TIMEZONE)->format('Y-m-d');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        // Un cliente cancellato resta l'intestatario delle sue prenotazioni.
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * Il cliente è obbligatorio (chiave esterna non nulla).
     */
    public function owner(): Customer
    {
        return $this->customer ?? throw new LogicException("Prenotazione {$this->id} senza cliente.");
    }

    /**
     * @return BelongsTo<ApiClient, $this>
     */
    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<Discount, $this>
     */
    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }

    /**
     * @return HasMany<ReservationLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ReservationLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<PaymentTransaction, $this>
     */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}
