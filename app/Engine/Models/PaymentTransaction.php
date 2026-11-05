<?php

namespace App\Engine\Models;

use Database\Engine\Factories\PaymentTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transazione presso il provider di pagamento (PayPal). order_id è unico per provider.
 *
 * @property int $id
 * @property string $reservation_id
 * @property string $provider
 * @property string $order_id
 * @property string|null $capture_id
 * @property string $status
 * @property int $amount_cents
 * @property string $currency
 * @property array<string, mixed>|null $payload
 */
#[UseFactory(PaymentTransactionFactory::class)]
class PaymentTransaction extends EngineModel
{
    /** @use HasFactory<PaymentTransactionFactory> */
    use HasFactory;

    protected $fillable = ['provider', 'order_id', 'capture_id', 'status', 'amount_cents', 'currency', 'payload'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'payload' => 'array'];
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
