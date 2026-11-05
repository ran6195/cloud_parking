@php
    use App\Services\Engine\EngineClient;
    use App\Support\SiteText;

    // Prezzi "a partire da" del motore: lo stesso listino dei preventivi.
    try {
        $summary = app(EngineClient::class)->tariffSummary();
    } catch (\App\Services\Engine\EngineUnavailable|\App\Services\Engine\EngineApiError) {
        $summary = null;
    }

    $labels = [1 => '1 giorno', 3 => '3 giorni', 7 => 'Una settimana', 14 => 'Due settimane'];
    $rows = [];
    foreach ($summary['prices'] ?? [] as $price) {
        $rows[] = [$labels[$price['days']] ?? $price['days'].' giorni', SiteText::euro($price['price']['amount_cents'] / 100), null];
    }
    if ($summary) {
        $rows[] = ['Ogni giorno oltre il 30°', '+ '.SiteText::euro($summary['price_per_day_after_30']['amount_cents'] / 100), 'al giorno'];
        if (($summary['covered_parking_percentage'] ?? 0) > 0) {
            $rows[] = ['Posto coperto', '+ '.$summary['covered_parking_percentage'].'%', 'sul prezzo del parcheggio'];
        }
    }
@endphp
<x-section :data="$data" default-background="carta" default-anchor="tariffe">
    <div class="grid gap-10 lg:grid-cols-2 lg:gap-16">
        <div>
            <h2 class="titolo-sezione">{{ $data['heading'] ?? '' }}</h2>
            @if(! empty($data['text']))
                <p class="mt-4 max-w-lg text-lg text-testo-soft">{{ SiteText::fill($data['text']) }}</p>
            @endif
            @if(! empty($data['button_label']) && ! empty($data['button_url']))
                <a href="{{ $data['button_url'] }}" class="btn-primario mt-8">
                    {{ $data['button_label'] }}
                    <x-icona name="arrow-right" class="size-5" />
                </a>
            @endif
        </div>

        <div class="rounded-card border border-bordo p-6 sm:p-8">
            @if($rows)
                <table class="w-full">
                    <caption class="pb-4 text-left font-bold text-testo-soft">{{ $data['table_caption'] ?? 'Posto scoperto, navetta inclusa' }}</caption>
                    <thead class="sr-only">
                        <tr><th scope="col">Durata</th><th scope="col">Prezzo</th></tr>
                    </thead>
                    <tbody class="divide-y divide-bordo border-t border-bordo">
                        @foreach($rows as [$label, $price, $unit])
                            <tr>
                                <th scope="row" class="py-4 pr-4 text-left text-lg font-normal">{{ $label }}</th>
                                <td class="py-4 text-right whitespace-nowrap">
                                    <span class="font-display text-2xl font-bold tracking-[-0.5px]">{{ $price }}</span>
                                    @if($unit)
                                        <span class="block text-sm text-testo-soft">{{ $unit }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="mt-4 text-sm text-testo-soft">Prezzi del listino in corso. Il prezzo esatto lo vedi inserendo le date.</p>
            @else
                <p class="text-lg">Il prezzo dipende dalle date: inseriscile nel modulo e lo vedi subito.</p>
            @endif
        </div>
    </div>
</x-section>
