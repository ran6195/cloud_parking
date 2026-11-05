<?php

namespace App\Engine\Models;

use App\Engine\Domain\Pricing\PriceTable;
use Carbon\CarbonImmutable;
use Database\Engine\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Listino valido da starts_on a ends_on (estremi inclusi). I periodi dei
 * listini attivi non si sovrappongono: lo garantisce un vincolo del database.
 *
 * @property int $id
 * @property string $name
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int $extra_day_cents
 * @property string $source
 */
#[UseFactory(PriceListFactory::class)]
class PriceList extends EngineModel
{
    /** @use HasFactory<PriceListFactory> */
    use HasFactory;

    use SoftDeletes;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_LEGACY = 'legacy_exceptions';

    protected $fillable = ['name', 'starts_on', 'ends_on', 'extra_day_cents', 'source'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'extra_day_cents' => 'integer',
        ];
    }

    /**
     * @return HasMany<Price, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class)->orderBy('days');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCovering(Builder $query, string $date): Builder
    {
        return $query->where('starts_on', '<=', $date)->where('ends_on', '>=', $date);
    }

    /**
     * Sostituisce i prezzi del listino.
     *
     * @param  array<int, int>  $centsByDays  prezzo totale in centesimi indicizzato per giorni (1..30)
     */
    public function syncPrices(array $centsByDays): void
    {
        $now = now();
        $this->prices()->delete();
        $this->prices()->insert(array_map(fn (int $days, int $cents) => [
            'price_list_id' => $this->id,
            'days' => $days,
            'amount_cents' => $cents,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys($centsByDays), $centsByDays));
        $this->unsetRelation('prices');
    }

    /**
     * @return array<int, int> centesimi indicizzati per giorni
     */
    public function centsByDays(): array
    {
        return $this->prices->mapWithKeys(fn (Price $price) => [$price->days => $price->amount_cents])->all();
    }

    /**
     * Listino di dominio. Fallisce se mancano dei giorni.
     */
    public function toPriceTable(): PriceTable
    {
        return PriceTable::fromCents($this->centsByDays(), $this->extra_day_cents, $this->name);
    }
}
