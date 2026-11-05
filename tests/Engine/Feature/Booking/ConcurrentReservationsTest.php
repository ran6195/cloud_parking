<?php

use App\Engine\Application\QuoteService;
use App\Engine\Application\ReservationInput;
use App\Engine\Application\ReservationService;
use App\Engine\Domain\Pricing\PaymentMethod;
use App\Engine\Domain\Pricing\QuoteRequest;
use App\Engine\Domain\Pricing\StayPeriod;
use App\Engine\Domain\Shared\EngineException;
use App\Engine\Models\ApiClient;
use App\Engine\Models\Reservation;
use App\Engine\Settings\EngineSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Engine\Support\EngineApi;

/*
 * Due processi veri, ciascuno con la propria connessione Postgres, provano a
 * prenotare nello stesso istante l'unico posto rimasto. Senza transazione di
 * test: i dati devono essere visibili a entrambi. Lo schema del database di
 * test viene ricreato prima e dopo.
 */

beforeEach(function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('Serve l\'estensione pcntl.');
    }

    expect(DB::connection('engine')->getDatabaseName())->toEndWith('_test');
    Artisan::call('engine:migrate', ['--fresh' => true, '--force' => true]);
});

afterEach(function () {
    DB::connection('engine')->reconnect();
    Artisan::call('engine:migrate', ['--fresh' => true, '--force' => true]);
});

it('non vende due volte l\'ultimo posto a richieste contemporanee', function () {
    EngineApi::freezeClock();
    EngineApi::novemberPriceList();
    $settings = app(EngineSettings::class);
    $settings->set('parking.total_spots', 1);
    $settings->set('parking.auto_block', true);
    $client = ApiClient::issue('Concorrenza', 'web')['client'];

    $quotes = app(QuoteService::class);
    $request = new QuoteRequest(StayPeriod::fromStrings('2026-11-20 08:00', '2026-11-23 08:00'), paymentMethod: PaymentMethod::Online);
    $quoteIds = [$quotes->create($request, $client)->id, $quotes->create($request, $client)->id];

    $results = sys_get_temp_dir().'/engine-concurrency-'.getmypid();
    @mkdir($results);
    $startAt = microtime(true) + 0.5;

    // Nessuna connessione aperta da ereditare: ogni figlio apre la propria.
    DB::disconnect('engine');

    $children = [];
    foreach ($quoteIds as $i => $quoteId) {
        $pid = pcntl_fork();
        if ($pid === 0) {
            time_sleep_until($startAt);
            try {
                app(ReservationService::class)->create(new ReservationInput(
                    quoteId: $quoteId,
                    customer: ['first_name' => "Cliente{$i}", 'last_name' => 'Test', 'email' => "cliente{$i}@example.com", 'phone' => '+39 333 000 000'.$i],
                    vehicle: ['plate' => "AA00{$i}BB"],
                    travel: ['passengers' => 1],
                    billing: null,
                    notes: null,
                    referral: null,
                    consents: ['privacy' => true, 'marketing' => false],
                ), ApiClient::query()->findOrFail($client->id));
                $outcome = 'ok';
            } catch (EngineException $e) {
                $outcome = $e->errorCode();
            } catch (Throwable $e) {
                $outcome = 'errore: '.$e->getMessage();
            }
            file_put_contents("{$results}/{$i}", $outcome);
            // Uscita immediata: niente distruttori né shutdown di PHPUnit nel processo figlio.
            posix_kill(posix_getpid(), SIGKILL);
        }
        $children[] = $pid;
    }

    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $outcomes = array_map(fn (int $i) => (string) @file_get_contents("{$results}/{$i}"), array_keys($quoteIds));
    array_map('unlink', glob("{$results}/*") ?: []);
    @rmdir($results);
    sort($outcomes);

    DB::connection('engine')->reconnect();

    expect($outcomes)->toBe(['dates_unavailable', 'ok'])
        ->and(Reservation::query()->count())->toBe(1);
});
