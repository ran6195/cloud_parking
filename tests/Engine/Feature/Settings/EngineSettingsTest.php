<?php

use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Models\Setting;
use App\Engine\Settings\EngineSettings;
use Tests\Concerns\RefreshEngineDatabase;

uses(RefreshEngineDatabase::class);

it('parte dai valori del legacy con la tabella vuota', function () {
    $settings = new EngineSettings;
    $rules = $settings->capacityRules();

    expect($settings->totalSpots())->toBe(285)
        ->and($settings->autoBlock())->toBeTrue()
        ->and($rules->capacityFor(ExtraId::CoveredParking))->toBe(3)
        ->and($rules->capacityFor(ExtraId::KeyDrop))->toBe(3)
        ->and($settings->paymentFees()->onSite->cents)->toBe(100)
        ->and($settings->cancellationHours())->toBe(24)
        ->and(array_map(fn ($e) => $e->id, $settings->catalog()->active()))->toBe([ExtraId::CoveredParking, ExtraId::KeyDrop]);
});

it('sovrappone ai predefiniti solo i campi salvati di un servizio', function () {
    (new EngineSettings)->set('extras.key_drop', ['price_cents' => 900]);

    $keyDrop = (new EngineSettings)->catalog()->get(ExtraId::KeyDrop);

    expect($keyDrop->price?->cents)->toBe(900)
        ->and($keyDrop->name)->toBe('Tieni le chiavi')
        ->and($keyDrop->capacityLimited)->toBeTrue();
});

it('svuota la cache quando un\'impostazione cambia, anche fuori da EngineSettings', function () {
    $settings = new EngineSettings;
    expect((new EngineSettings)->totalSpots())->toBe(285);

    Setting::query()->create(['key' => 'parking.total_spots', 'value' => 120]);
    expect((new EngineSettings)->totalSpots())->toBe(120);

    $settings->set('parking.auto_block', false);
    expect((new EngineSettings)->autoBlock())->toBeFalse();
});

it('rifiuta le chiavi sconosciute', function () {
    expect(fn () => (new EngineSettings)->set('parking.posti', 3))->toThrow(InvalidArgumentException::class);
});
