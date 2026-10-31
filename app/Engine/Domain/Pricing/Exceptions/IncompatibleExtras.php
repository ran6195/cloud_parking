<?php

namespace App\Engine\Domain\Pricing\Exceptions;

use App\Engine\Domain\Pricing\ExtraDefinition;
use App\Engine\Domain\Shared\EngineException;

final class IncompatibleExtras extends EngineException
{
    public static function between(ExtraDefinition $a, ExtraDefinition $b): self
    {
        return new self("{$a->name} e {$b->name} non possono essere scelti insieme.");
    }

    public function errorCode(): string
    {
        return 'extras_incompatible';
    }
}
