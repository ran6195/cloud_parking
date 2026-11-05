<?php

namespace App\Engine\Models;

use App\Engine\EngineServiceProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Base dei modelli del motore: tutti vivono sulla connessione "engine" (schema Postgres dedicato).
 *
 * Le date si salvano con l'offset: le colonne timestamptz restano corrette
 * qualunque sia il fuso dell'app (Europe/Rome) o della sessione Postgres.
 */
abstract class EngineModel extends Model
{
    public const TIMEZONE = 'Europe/Rome';

    /** @var string */
    protected $connection = EngineServiceProvider::CONNECTION;

    /** @var string */
    protected $dateFormat = 'Y-m-d H:i:sP';
}
