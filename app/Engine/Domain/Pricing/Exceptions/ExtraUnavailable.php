<?php

namespace App\Engine\Domain\Pricing\Exceptions;

use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Shared\EngineException;

final class ExtraUnavailable extends EngineException
{
    public static function for(ExtraId $id): self
    {
        return new self("Il servizio [{$id->value}] non è attivo.");
    }

    public function errorCode(): string
    {
        return 'extra_unavailable';
    }
}
