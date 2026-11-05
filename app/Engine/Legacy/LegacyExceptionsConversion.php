<?php

namespace App\Engine\Legacy;

final readonly class LegacyExceptionsConversion
{
    /**
     * @param  list<LegacyMonthlyPriceList>  $priceLists
     * @param  list<string>  $skipped  mesi scartati, con il motivo
     * @param  list<string>  $warnings  anomalie che non impediscono l'import
     */
    public function __construct(
        public array $priceLists,
        public array $skipped,
        public array $warnings,
    ) {}
}
