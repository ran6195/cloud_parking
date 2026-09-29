@php
    use App\Support\SiteText;

    $showForm = $data['show_booking_form'] ?? true;
    $showSign = $data['show_sign'] ?? true;
    $imageUrl = $showForm ? null : \App\Support\Uploads::url($data['image'] ?? null);
@endphp
<section class="relative bg-terra">
    {{-- Su mobile il fondo scuro si ferma prima della fine: la card del modulo sborda sotto l'hero. --}}
    <div aria-hidden="true" class="absolute inset-x-0 top-0 bg-fondo {{ $showForm ? 'bottom-40 lg:bottom-0' : 'bottom-0' }}"></div>

    <div class="contenitore relative grid items-center gap-10 pt-12 sm:pt-16 lg:grid-cols-[1.1fr_1fr] lg:gap-16 lg:py-20 {{ $showForm ? 'pb-0' : 'pb-16' }}">
        <div>
            <h1 class="font-display text-[2.5rem] leading-[1.05] font-extrabold tracking-[-1px] text-carta sm:text-5xl lg:text-[3.75rem] lg:tracking-[-1.5px]">
                {{ SiteText::fill($data['heading'] ?? '') }}
            </h1>

            @if(! empty($data['subheading']))
                <p class="mt-5 max-w-xl text-lg leading-relaxed text-testo-chiaro sm:text-xl">{{ SiteText::fill($data['subheading']) }}</p>
            @endif

            @if($showSign && ! empty($data['sign_title']))
                <div class="mt-8 flex max-w-lg items-stretch gap-1 rounded-[6px] bg-segnale p-2 text-fondo">
                    <span class="grid w-16 shrink-0 place-items-center rounded-[4px] bg-fondo sm:w-[4.5rem]">
                        <x-icona name="plane" class="size-9 text-segnale sm:size-10" stroke-width="2" />
                    </span>
                    <span class="flex min-w-0 flex-1 flex-col justify-center px-3 py-2 sm:px-4">
                        <span class="font-display text-xl leading-tight font-extrabold tracking-[-0.5px] sm:text-2xl">{{ $data['sign_title'] }}</span>
                        @if(! empty($data['sign_text']))
                            <span class="mt-0.5 text-sm leading-snug font-bold sm:text-base">{{ SiteText::fill($data['sign_text']) }}</span>
                        @endif
                    </span>
                    <span class="grid shrink-0 place-items-center pr-1 sm:pr-2">
                        <x-icona name="arrow-right" class="size-10 sm:size-12" stroke-width="3" />
                    </span>
                </div>
            @endif

            @if(! empty($data['cta_label']) && ! empty($data['cta_url']))
                <a href="{{ $data['cta_url'] }}" class="btn-segnale mt-8">{{ $data['cta_label'] }}</a>
            @endif
        </div>

        @if($showForm)
            <div id="prenota" class="relative">
                <livewire:booking-form />
            </div>
        @elseif($imageUrl)
            <img src="{{ $imageUrl }}" alt="" class="w-full rounded-card" width="800" height="600" fetchpriority="high">
        @endif
    </div>
</section>
