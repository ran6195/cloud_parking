<?php

namespace App\Engine\Models;

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Pricing\Discount\Discount as DomainDiscount;
use App\Engine\Domain\Pricing\Discount\DiscountCode;
use App\Engine\Domain\Pricing\Discount\DiscountType;
use App\Engine\Domain\Shared\Money;
use Carbon\CarbonImmutable;
use Database\Engine\Factories\DiscountFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Codice sconto. Il codice è salvato normalizzato (maiuscolo, senza spazi esterni).
 *
 * @property int $id
 * @property string $code
 * @property DiscountType $type
 * @property int|null $percentage
 * @property int|null $amount_cents
 * @property int|null $max_uses null = illimitati
 * @property CarbonImmutable|null $valid_until ultimo giorno valido, incluso
 * @property int|null $legacy_id
 */
#[UseFactory(DiscountFactory::class)]
class Discount extends EngineModel
{
    /** @use HasFactory<DiscountFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['code', 'type', 'percentage', 'amount_cents', 'max_uses', 'valid_until', 'legacy_id'];

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'percentage' => 'integer',
            'amount_cents' => 'integer',
            'max_uses' => 'integer',
            'valid_until' => 'immutable_date',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn (string $value) => (new DiscountCode($value))->value);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Utilizzi effettivi: prenotazioni valide (confermate, pagate o concluse).
     */
    public function timesUsed(): int
    {
        return $this->reservations()->whereIn('status', ReservationStatus::valid())->count();
    }

    public function toDomain(?int $timesUsed = null): DomainDiscount
    {
        return new DomainDiscount(
            code: new DiscountCode($this->code),
            type: $this->type,
            percentage: $this->percentage,
            amount: $this->amount_cents === null ? null : Money::cents($this->amount_cents),
            validUntil: $this->valid_until?->toDateTimeImmutable(),
            maxUses: $this->max_uses,
            timesUsed: $timesUsed ?? $this->timesUsed(),
        );
    }
}
