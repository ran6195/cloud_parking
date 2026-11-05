<?php

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Pricing\Discount\DiscountType;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Filament\Pages\EngineSettingsPage;
use App\Engine\Filament\Pages\QuoteSimulator;
use App\Engine\Filament\Resources\ApiClients\Pages\ManageApiClients;
use App\Engine\Filament\Resources\BlockedDates\Pages\ManageBlockedDates;
use App\Engine\Filament\Resources\Discounts\Pages\ManageDiscounts;
use App\Engine\Filament\Resources\PriceLists\Pages\CreatePriceList;
use App\Engine\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Engine\Filament\Resources\PriceLists\Pages\ListPriceLists;
use App\Engine\Filament\Resources\Reservations\Pages\ViewReservation;
use App\Engine\Filament\Widgets\EngineStatsWidget;
use App\Engine\Filament\Widgets\OccupancyWidget;
use App\Engine\Models\Admin;
use App\Engine\Models\ApiClient;
use App\Engine\Models\BlockedDate;
use App\Engine\Models\Discount;
use App\Engine\Models\PaymentTransaction;
use App\Engine\Models\PriceList;
use App\Engine\Models\Reservation;
use App\Engine\Settings\EngineSettings;
use App\Models\User;
use Database\Engine\Factories\PriceListFactory;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\RefreshEngineDatabase;
use Tests\Engine\Support\EngineApi;

uses(RefreshEngineDatabase::class);

beforeEach(function () {
    Mail::fake();
    EngineApi::freezeClock();
    $this->admin = Admin::factory()->create();
    $this->actingAs($this->admin, 'engine');
    Filament::setCurrentPanel(Filament::getPanel('motore'));
});

/** @return array<int, string> 30 prezzi in euro: N giorni = 10 € + 5 € × N */
function euroPrices(): array
{
    return array_map(fn (int $cents) => number_format($cents / 100, 2, '.', ''), PriceListFactory::linearCents());
}

it('apre tutte le pagine del pannello', function () {
    EngineApi::novemberPriceList();
    $reservation = Reservation::factory()->withExtra('covered_parking')->create();
    Discount::factory()->create();
    BlockedDate::factory()->create(['date' => '2026-11-10']);
    ApiClient::issue('Form', 'web');

    foreach ([
        '/motore', '/motore/prenotazioni', "/motore/prenotazioni/{$reservation->id}", "/motore/prenotazioni/{$reservation->id}/edit",
        '/motore/clienti', '/motore/listini', '/motore/listini/create', '/motore/listini/'.PriceList::query()->value('id').'/edit',
        '/motore/codici-sconto', '/motore/date-bloccate', '/motore/client-api', '/motore/impostazioni', '/motore/simulatore',
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('tiene separati gli utenti del pannello e quelli del CMS', function () {
    app('auth')->forgetGuards();
    $this->get('/motore/prenotazioni')->assertRedirect('/motore/login');

    app('auth')->forgetGuards();
    $this->actingAs(User::factory()->make(['id' => 1]), 'web');
    $this->get('/motore/prenotazioni')->assertRedirect('/motore/login');
});

it('crea un listino con i 30 prezzi in euro', function () {
    Livewire::test(CreatePriceList::class)
        ->fillForm(['name' => 'Dicembre 2026', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-31', 'prices' => euroPrices(), 'extra_day' => '9.50'])
        ->call('create')
        ->assertHasNoFormErrors();

    $priceList = PriceList::query()->sole();
    expect($priceList->toPriceTable()->totalFor(1)->cents)->toBe(1500)
        ->and($priceList->toPriceTable()->totalFor(30)->cents)->toBe(16000)
        ->and($priceList->extra_day_cents)->toBe(950)
        ->and($priceList->source)->toBe(PriceList::SOURCE_MANUAL);
});

it('rifiuta un listino che si sovrappone o con prezzi mancanti', function () {
    EngineApi::novemberPriceList();
    $prices = euroPrices();
    unset($prices[30]);

    Livewire::test(CreatePriceList::class)
        ->fillForm(['name' => 'Sovrapposto', 'starts_on' => '2026-11-20', 'ends_on' => '2026-12-10', 'prices' => $prices, 'extra_day' => '10'])
        ->call('create')
        ->assertHasFormErrors(['ends_on', 'prices.30' => 'required']);

    expect(PriceList::query()->count())->toBe(1);
});

it('modifica i prezzi di un listino', function () {
    $priceList = EngineApi::novemberPriceList();

    Livewire::test(EditPriceList::class, ['record' => $priceList->getRouteKey()])
        ->assertSchemaStateSet(['prices.3' => '27.00', 'extra_day' => '10.00'])
        ->fillForm(['prices' => array_replace(array_map(fn (int $cents) => number_format($cents / 100, 2, '.', ''), $priceList->centsByDays()), [3 => '28.50'])])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($priceList->fresh()?->toPriceTable()->totalFor(3)->cents)->toBe(2850);
});

it('duplica un listino su un nuovo periodo con un aumento', function () {
    $priceList = EngineApi::novemberPriceList();

    Livewire::test(ListPriceLists::class)
        ->callTableAction('duplicate', $priceList, data: ['name' => 'Novembre 2027', 'starts_on' => '2027-11-01', 'ends_on' => '2027-11-30', 'increase' => 10])
        ->assertHasNoTableActionErrors();

    $copy = PriceList::query()->where('name', 'Novembre 2027')->sole();
    expect($copy->toPriceTable()->totalFor(1)->cents)->toBe(1870)
        ->and($copy->extra_day_cents)->toBe(1100);
});

it('crea codici sconto senza duplicati', function () {
    Livewire::test(ManageDiscounts::class)
        ->callAction('create', data: ['code' => 'estate10', 'type' => 'percentage', 'percentage' => 10, 'max_uses' => 50])
        ->assertHasNoActionErrors();
    Livewire::test(ManageDiscounts::class)
        ->callAction('create', data: ['code' => ' ESTATE10 ', 'type' => 'percentage', 'percentage' => 5])
        ->assertHasActionErrors(['code']);
    Livewire::test(ManageDiscounts::class)
        ->callAction('create', data: ['code' => 'cinque', 'type' => 'fixed_amount', 'amount' => '5.00'])
        ->assertHasNoActionErrors();

    expect(Discount::query()->where('code', 'ESTATE10')->sole()->max_uses)->toBe(50)
        ->and(Discount::query()->where('code', 'CINQUE')->sole())
        ->type->toBe(DiscountType::FixedAmount)
        ->amount_cents->toBe(500)
        ->percentage->toBeNull();
});

it('blocca un periodo di giorni senza duplicare quelli già bloccati', function () {
    BlockedDate::factory()->create(['date' => '2026-12-24']);

    Livewire::test(ManageBlockedDates::class)
        ->callAction('blockPeriod', data: ['from' => '2026-12-23', 'to' => '2026-12-26', 'reason' => 'Chiusura natalizia'])
        ->assertHasNoActionErrors();

    expect(BlockedDate::query()->orderBy('date')->get()->map(fn (BlockedDate $d) => $d->date->format('m-d'))->all())
        ->toBe(['12-23', '12-24', '12-25', '12-26']);
});

it('crea un client API dal pannello', function () {
    Livewire::test(ManageApiClients::class)
        ->callAction('issue', data: ['name' => 'PWA', 'channel' => 'pwa', 'allowed_origins' => ['https://App.Example/'], 'rate_limit_per_minute' => 60])
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect(ApiClient::query()->sole())
        ->channel->toBe('pwa')
        ->allowed_origins->toBe(['https://app.example'])
        ->rate_limit_per_minute->toBe(60);
});

it('salva le impostazioni del gestore', function () {
    Livewire::test(EngineSettingsPage::class)
        ->assertSchemaStateSet(['total_spots' => 285, 'extras.key_drop.price' => '7.00', 'refunds_mode' => 'manual'])
        ->fillForm([
            'total_spots' => 300,
            'on_site_fee' => '1.50',
            'refunds_mode' => 'automatic',
            'contact_email' => 'gestore@example.com',
            'extras.key_drop.price' => '8',
            'extras.covered_parking.capacity' => 5,
            'extras.exterior_wash.active' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = new EngineSettings;
    expect($settings->totalSpots())->toBe(300)
        ->and($settings->paymentFees()->onSite->cents)->toBe(150)
        ->and($settings->automaticRefunds())->toBeTrue()
        ->and($settings->managerEmail())->toBe('gestore@example.com')
        ->and($settings->extra(ExtraId::KeyDrop)['price_cents'])->toBe(800)
        ->and($settings->capacityRules()->capacityFor(ExtraId::CoveredParking))->toBe(5)
        ->and(array_map(fn ($e) => $e->id->value, $settings->catalog()->active()))->toBe(['covered_parking', 'exterior_wash', 'key_drop']);
});

it('simula un preventivo con il dettaglio del calcolo', function () {
    EngineApi::novemberPriceList();

    Livewire::test(QuoteSimulator::class)
        ->fillForm(['start' => '2026-11-20 08:00', 'end' => '2026-11-23 07:00', 'extras' => ['covered_parking'], 'payment_method' => 'on_site'])
        ->call('calculate')
        ->assertHasNoFormErrors()
        ->assertSet('result.total_cents', 3880)
        ->assertSet('result.trace.price_table', 'Novembre 2026')
        ->assertSee('38,80 €')
        ->assertSee('prenotabile');

    Livewire::test(QuoteSimulator::class)
        ->fillForm(['start' => '2033-11-20 08:00', 'end' => '2033-11-23 07:00', 'payment_method' => 'on_site'])
        ->call('calculate')
        ->assertSet('error', 'Non esiste un listino per il 20/11/2033.');
});

it('annulla una prenotazione pagata online e gestisce il rimborso dal dettaglio', function () {
    $reservation = Reservation::factory()->status(ReservationStatus::Paid)->create([
        'payment_method' => 'online', 'paid_at' => now(), 'subtotal_cents' => 3780, 'total_cents' => 3780,
    ]);
    PaymentTransaction::factory()->create(['reservation_id' => $reservation->id, 'capture_id' => 'CAP-9', 'amount_cents' => 3780]);

    Livewire::test(ViewReservation::class, ['record' => $reservation->getRouteKey()])
        ->callAction('cancel', data: ['reason' => 'Richiesta telefonica', 'notify' => true])
        ->assertNotified('Prenotazione annullata.');

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Cancelled)
        ->and($reservation->refund['status'])->toBe('requested')
        ->and($reservation->refund['tax_code'])->toBeNull();

    Livewire::test(ViewReservation::class, ['record' => $reservation->getRouteKey()])
        ->assertActionVisible('markRefunded')
        ->callAction('markRefunded', data: ['note' => 'Bonifico'])
        ->assertActionHidden('markRefunded');

    expect($reservation->refresh()->refund)->toMatchArray(['status' => 'refunded', 'mode' => 'manual', 'manager_note' => 'Bonifico']);
});

it('annulla dal pannello anche oltre la finestra del cliente, e conclude le soste', function () {
    $late = Reservation::factory()->between('2026-11-05 18:00', '2026-11-08 08:00')->create();
    $done = Reservation::factory()->between('2026-11-01 08:00', '2026-11-03 08:00')->create();

    Livewire::test(ViewReservation::class, ['record' => $late->getRouteKey()])->callAction('cancel', data: ['notify' => false]);
    Livewire::test(ViewReservation::class, ['record' => $done->getRouteKey()])->callAction('complete');

    expect($late->refresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($done->refresh()->status)->toBe(ReservationStatus::Completed);
    Mail::assertNothingQueued();
});

it('mostra occupazione e numeri della giornata', function () {
    app(EngineSettings::class)->set('extras.covered_parking', ['capacity' => 4]);
    Reservation::factory()->between('2026-11-05 08:00', '2026-11-07 08:00')->withExtra('covered_parking')->create();
    Reservation::factory()->between('2026-11-06 08:00', '2026-11-09 08:00')->create();
    BlockedDate::factory()->create(['date' => '2026-11-12']);

    Livewire::test(OccupancyWidget::class)
        ->assertViewHas('rows', fn (array $rows) => count($rows) === 14
            && $rows[0]['parking'] === 1 && $rows[1]['parking'] === 2 && $rows[1]['covered'] === 1 && $rows[7]['blocked'] === true)
        ->assertSee('1 / 4')
        ->assertSee('bloccato');

    Livewire::test(EngineStatsWidget::class)->assertSee('1 / 285');
});

it('crea gli utenti del pannello da riga di comando', function () {
    $this->artisan('engine:admin', ['email' => 'Gestore@Example.com', '--name' => 'Gestore', '--password' => 'una-password-lunga'])
        ->expectsOutputToContain('creato')->assertSuccessful();
    $this->artisan('engine:admin', ['email' => 'gestore@example.com', '--password' => 'corta'])->assertFailed();

    $admin = Admin::query()->where('email', 'gestore@example.com')->sole();
    expect(Hash::check('una-password-lunga', $admin->password))->toBeTrue()
        ->and($admin->canAccessPanel(Filament::getPanel('motore')))->toBeTrue()
        ->and($admin->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});
