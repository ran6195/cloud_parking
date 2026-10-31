<?php

namespace App\Engine\Domain\Shared;

use DomainException;

/**
 * Errore di dominio con un codice stabile, lo stesso esposto dall'API
 * nel campo "code" dei problem+json (vedi openapi/v1.yaml).
 */
abstract class EngineException extends DomainException
{
    abstract public function errorCode(): string;
}
