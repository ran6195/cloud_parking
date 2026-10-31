<?php

namespace App\Engine\Domain\Pricing\Exceptions;

use App\Engine\Domain\Shared\EngineException;
use DateTimeImmutable;

final class PriceListNotFound extends EngineException
{
    public static function forDate(DateTimeImmutable $date): self
    {
        return new self('Non esiste un listino per il '.$date->format('d/m/Y').'.');
    }

    public function errorCode(): string
    {
        return 'price_list_not_found';
    }
}
