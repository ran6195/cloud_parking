<?php

namespace App\Engine\Application\Exceptions;

use App\Engine\Domain\Shared\EngineException;

/**
 * Il periodo o un servizio a capacità limitata non è più disponibile (409).
 */
final class DatesUnavailable extends EngineException
{
    /**
     * @param  list<string>  $unavailableDates
     */
    public function __construct(string $message, public readonly array $unavailableDates)
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'dates_unavailable';
    }
}
