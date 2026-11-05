<?php

namespace App\Engine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prezzo totale di un listino per un numero di giorni (1..30).
 *
 * @property int $id
 * @property int $price_list_id
 * @property int $days
 * @property int $amount_cents
 */
class Price extends EngineModel
{
    protected $fillable = ['days', 'amount_cents'];

    protected function casts(): array
    {
        return ['days' => 'integer', 'amount_cents' => 'integer'];
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
