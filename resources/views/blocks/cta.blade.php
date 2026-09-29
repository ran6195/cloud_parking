<section @if(! empty($data['anchor'])) id="{{ $data['anchor'] }}" @endif class="bg-segnale py-16 text-fondo sm:py-20">
    <div class="contenitore flex flex-col items-start gap-8 lg:flex-row lg:items-center lg:justify-between">
        <div class="max-w-2xl">
            <h2 class="titolo-sezione">{{ \App\Support\SiteText::fill($data['heading'] ?? '') }}</h2>
            @if(! empty($data['text']))
                <p class="mt-3 text-lg">{{ \App\Support\SiteText::fill($data['text']) }}</p>
            @endif
        </div>
        @if(! empty($data['button_label']) && ! empty($data['button_url']))
            <a href="{{ $data['button_url'] }}" class="btn-primario shrink-0 px-7 text-lg">
                {{ $data['button_label'] }}
                <x-icona name="arrow-right" class="size-5" />
            </a>
        @endif
    </div>
</section>
