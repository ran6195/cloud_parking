<?php

namespace App\Engine\Models;

use App\Engine\Settings\EngineSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Impostazione del motore (chiave → valore JSON). Si legge con EngineSettings,
 * che tiene i valori in cache: ogni modifica la svuota.
 *
 * @property string $key
 * @property mixed $value
 */
class Setting extends EngineModel
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(EngineSettings::CACHE_KEY));
        static::deleted(fn () => Cache::forget(EngineSettings::CACHE_KEY));
    }

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
