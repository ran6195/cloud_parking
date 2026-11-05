<?php

namespace App\Engine\Models;

/**
 * Impostazione del motore (chiave → valore JSON). La lettura tipizzata e in
 * cache arriva con EngineSettings (fase 5).
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

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
