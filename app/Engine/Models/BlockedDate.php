<?php

namespace App\Engine\Models;

use Carbon\CarbonImmutable;
use Database\Engine\Factories\BlockedDateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Giorno in cui non si accettano soste.
 *
 * @property int $id
 * @property CarbonImmutable $date
 * @property string|null $reason
 */
#[UseFactory(BlockedDateFactory::class)]
class BlockedDate extends EngineModel
{
    /** @use HasFactory<BlockedDateFactory> */
    use HasFactory;

    protected $fillable = ['date', 'reason'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date'];
    }
}
