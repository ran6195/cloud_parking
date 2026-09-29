@php
    use App\Support\SiteText;

    $parking = app(\App\Settings\ParkingSettings::class);
    $rows = [
        ['Fino a 3 giorni', SiteText::euro($parking->price_up_to_3_days), null],
        ['Una settimana', SiteText::euro($parking->price_week), null],
        ['Due settimane', SiteText::euro($parking->price_two_weeks), null],
        ['Ogni giorno in più', '+ '.SiteText::euro($parking->price_extra_day), 'al giorno'],
    ];
    if ($parking->price_covered_per_day !== null) {
        $rows[] = ['Posto coperto', '+ '.SiteText::euro($parking->price_covered_per_day), 'al giorno'];
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
        </div>
    </div>
</x-section>
