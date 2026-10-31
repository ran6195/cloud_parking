<?php

namespace App\Engine\Domain\Pricing\Exceptions;

use App\Engine\Domain\Shared\EngineException;

final class InvalidStay extends EngineException
{
    public static function endNotAfterStart(): self
    {
        return new self('Il rientro deve essere successivo alla partenza.');
    }

    public static function tooLong(int $maxDays): self
    {
        return new self("La sosta non può superare {$maxDays} giorni.");
    }

    public function errorCode(): string
    {
        return 'validation_failed';
    }
}
