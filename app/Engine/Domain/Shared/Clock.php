<?php

namespace App\Engine\Domain\Shared;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
