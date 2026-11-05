<?php

namespace App\Engine\Models;

use App\Engine\Domain\Pricing\QuoteLine;
use App\Engine\Domain\Pricing\QuoteLineType;
use Database\Engine\Factories\ReservationLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riga della prenotazione, copiata dal preventivo. unit_cents è null per le voci forfettarie.
 *
 * @property int $id
 * @property string $reservation_id
 * @property int $position
 * @property QuoteLineType $type
 * @property string $code
 * @property string $description
 * @property int $quantity
 * @property int|null $unit_cents
 * @property int $total_cents
 */
#[UseFactory(ReservationLineFactory::class)]
class ReservationLine extends EngineModel
{
    /** @use HasFactory<ReservationLineFactory> */
    use HasFactory;

    protected $fillable = ['position', 'type', 'code', 'description', 'quantity', 'unit_cents', 'total_cents'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'type' => QuoteLineType::class,
            'quantity' => 'integer',
            'unit_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function attributesFrom(QuoteLine $line, int $position): array
    {
        return [
            'position' => $position,
            'type' => $line->type,
            'code' => $line->code,
            'description' => $line->description,
            'quantity' => $line->quantity,
            'unit_cents' => $line->unitPrice?->cents,
            'total_cents' => $line->total->cents,
        ];
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
