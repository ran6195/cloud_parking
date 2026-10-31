<?php

use App\Engine\Domain\Pricing\Exceptions\ExtraUnavailable;
use App\Engine\Domain\Pricing\Exceptions\IncompatibleExtras;
use App\Engine\Domain\Pricing\ExtraDefinition;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\ExtraPricing;
use Tests\Engine\Support\Pricing;

it('elenca solo i servizi attivi, in ordine', function () {
    $ids = array_map(fn (ExtraDefinition $e) => $e->id, Pricing::catalog()->active());

    expect($ids)->toBe([ExtraId::CoveredParking, ExtraId::ExteriorWash, ExtraId::KeyDrop, ExtraId::EvCharging]);
});

it('ordina e deduplica la selezione', function () {
    $resolved = Pricing::catalog()->resolve([ExtraId::EvCharging, ExtraId::ExteriorWash, ExtraId::EvCharging]);

    expect(array_map(fn (ExtraDefinition $e) => $e->id, $resolved))->toBe([ExtraId::ExteriorWash, ExtraId::EvCharging]);
});

it('rifiuta posto coperto insieme a tieni le chiavi', function () {
    Pricing::catalog()->resolve([ExtraId::KeyDrop, ExtraId::CoveredParking]);
})->throws(IncompatibleExtras::class, 'Posto coperto e Tieni le chiavi non possono essere scelti insieme.');

it('rifiuta un servizio non attivo', function () {
    Pricing::catalog()->resolve([ExtraId::InteriorWash]);
})->throws(ExtraUnavailable::class);

it('richiede un prezzo per i servizi a prezzo fisso', function () {
    new ExtraDefinition(ExtraId::ExteriorWash, 'Lavaggio', ExtraPricing::Fixed);
})->throws(InvalidArgumentException::class);

it('richiede una percentuale per i supplementi', function () {
    new ExtraDefinition(ExtraId::CoveredParking, 'Coperto', ExtraPricing::PercentageOfParking);
})->throws(InvalidArgumentException::class);

it('i codici di errore sono quelli del contratto API', function () {
    expect(ExtraUnavailable::for(ExtraId::InteriorWash)->errorCode())->toBe('extra_unavailable');
});
