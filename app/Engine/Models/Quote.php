<?php

namespace App\Engine\Models;

use App\Engine\Domain\Pricing\PaymentMethod;
use Carbon\CarbonImmutable;
use Database\Engine\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Preventivo salvato: le interfacce prenotano solo a partire da un preventivo valido.
 *
 * @property string $id
 * @property int|null $api_client_id
 * @property int|null $discount_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property list<string> $extras
 * @property PaymentMethod $payment_method
 * @property list<array<string, mixed>> $lines
 * @property int $subtotal_cents
 * @property int $discount_cents
 * @property int $total_cents
 * @property array<string, mixed>|null $discount
 * @property array<string, mixed> $trace
 * @property array{start: string, end: string, duration_minutes: int, billable_days: int, days_after_30: int} $stay
 * @property array<string, mixed> $availability
 * @property bool $bookable
 * @property CarbonImmutable $cancellation_free_until
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 */
#[UseFactory(QuoteFactory::class)]
class Quote extends EngineModel
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'api_client_id', 'discount_id', 'starts_at', 'ends_at', 'extras', 'payment_method',
        'lines', 'subtotal_cents', 'discount_cents', 'total_cents', 'discount', 'trace',
        'stay', 'availability', 'bookable', 'cancellation_free_until', 'expires_at', 'used_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'extras' => 'array',
            'payment_method' => PaymentMethod::class,
            'lines' => 'array',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'discount' => 'array',
            'trace' => 'array',
            'stay' => 'array',
            'availability' => 'array',
            'bookable' => 'boolean',
            'cancellation_free_until' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
        ];
    }

    public function isExpired(?CarbonImmutable $now = null): bool
    {
        return $this->expires_at->lessThanOrEqualTo($now ?? CarbonImmutable::now());
    }

    /**
     * @return BelongsTo<ApiClient, $this>
     */
    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    /**
     * @return BelongsTo<Discount, $this>
     */
    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }

    /**
     * @return HasOne<Reservation, $this>
     */
    public function reservation(): HasOne
    {
        return $this->hasOne(Reservation::class);
    }
}
