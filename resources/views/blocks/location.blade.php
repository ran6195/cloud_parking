@php
    use App\Support\SiteText;

    $general = app(\App\Settings\GeneralSettings::class);
    $parking = app(\App\Settings\ParkingSettings::class);
    $directions = $data['directions'] ?? [];
    if (($data['show_hours'] ?? true) && $general->opening_hours) {
        $directions[] = ['term' => 'Orari di apertura', 'description' => $general->opening_hours];
    }
@endphp
<x-section :data="$data" default-background="terra" default-anchor="dove">
    <div class="grid gap-10 lg:grid-cols-2 lg:gap-16">
        <div>
            <h2 class="titolo-sezione">{{ $data['heading'] ?? '' }}</h2>

            @if($general->operational_address)
                <address class="mt-6 flex items-start gap-3 text-lg font-bold not-italic">
                    <x-icona name="map-pin" class="mt-0.5 size-6 text-fondo" />
                    {{ $general->operational_address }}
                </address>
            @endif

            @if(! empty($data['text']))
                <p class="mt-4 text-testo-soft">{{ SiteText::fill($data['text']) }}</p>
            @endif

            @if(! empty($directions))
                <dl class="mt-8 divide-y divide-bordo border-y border-bordo">
                    @foreach($directions as $item)
                        <div class="py-4 sm:grid sm:grid-cols-[11rem_1fr] sm:gap-4">
                            <dt class="font-bold">{{ $item['term'] ?? '' }}</dt>
                            <dd class="mt-1 text-testo-soft sm:mt-0">{{ SiteText::fill($item['description'] ?? '') }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            @if($parking->maps_url)
                <a href="{{ $parking->maps_url }}" target="_blank" rel="noopener" class="btn-outline mt-8">
                    {{ $data['button_label'] ?? 'Apri in Google Maps' }}
                    <x-icona name="external-link" class="size-5" />
                    <span class="sr-only">(si apre in una nuova scheda)</span>
                </a>
            @endif
        </div>

        {{-- La mappa di Google viene caricata solo su richiesta: niente cookie di terze parti né peso inutile. --}}
        <div x-data="{ caricata: false }" class="relative min-h-80 overflow-hidden rounded-card border border-bordo bg-carta lg:min-h-0">
            @if($parking->maps_embed_url)
                <template x-if="caricata">
                    <iframe src="{{ $parking->maps_embed_url }}" title="Mappa: {{ $general->operational_address ?: 'posizione del parcheggio' }}"
                            class="absolute inset-0 size-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                </template>
            @endif
            <div x-show="! caricata" class="absolute inset-0 flex flex-col items-center justify-center gap-4 p-8 text-center">
                <span class="grid size-16 place-items-center rounded-full bg-segnale text-fondo">
                    <x-icona name="map-pin" class="size-8" />
                </span>
                @if($parking->maps_embed_url)
                    <p class="max-w-xs text-testo-soft">La mappa è fornita da Google e viene caricata solo se la apri.</p>
                    <button type="button" class="btn-primario" x-on:click="caricata = true">Mostra la mappa</button>
                @else
                    <p class="max-w-xs text-testo-soft">{{ $general->operational_address ?: 'Mappa in arrivo.' }}</p>
                @endif
            </div>
        </div>
    </div>
</x-section>
