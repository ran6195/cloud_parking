@php
    use App\Support\SiteText;

    $menuItems = $settings->menu_items ?? [];
    $hasCta = $settings->header_cta_label && $settings->header_cta_url;
@endphp
<header class="sticky top-0 z-40 bg-fondo text-carta"
        x-data="{ aperto: false }"
        x-on:keydown.escape.window="if (aperto) { aperto = false; $refs.pulsanteMenu.focus() }"
        x-on:click.outside="aperto = false">
    <div class="contenitore flex h-[72px] items-center justify-between gap-6">
        <a href="{{ url('/') }}" class="rounded-controllo">
            <x-logo :name="$settings->site_name" />
        </a>

        @if(! empty($menuItems))
            <nav aria-label="Principale" class="hidden lg:block">
                <ul class="flex items-center gap-7">
                    @foreach($menuItems as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="inline-flex min-h-11 items-center font-bold text-testo-chiaro hover:text-carta">{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <div class="hidden items-center gap-5 lg:flex">
            @if($settings->phone)
                <a href="{{ SiteText::telHref($settings->phone) }}" class="inline-flex min-h-11 items-center gap-2 font-bold hover:text-segnale">
                    <x-icona name="phone" class="size-5" />
                    {{ $settings->phone }}
                </a>
            @endif
            @if($hasCta)
                <a href="{{ $settings->header_cta_url }}" class="btn-segnale">{{ $settings->header_cta_label }}</a>
            @endif
        </div>

        <div class="flex items-center gap-2 lg:hidden">
            @if($settings->phone)
                <a href="{{ SiteText::telHref($settings->phone) }}" class="grid size-11 place-items-center rounded-controllo bg-segnale text-fondo">
                    <x-icona name="phone" class="size-5" />
                    <span class="sr-only">Chiama il {{ $settings->phone }}</span>
                </a>
            @endif
            <button type="button" x-ref="pulsanteMenu"
                    class="grid size-11 place-items-center rounded-controllo border border-white/25 hover:bg-fondo-scuro"
                    aria-controls="menu-mobile" aria-label="Apri il menu"
                    x-bind:aria-expanded="aperto ? 'true' : 'false'"
                    x-bind:aria-label="aperto ? 'Chiudi il menu' : 'Apri il menu'"
                    x-on:click="aperto = ! aperto">
                <x-icona name="menu" class="size-6" x-show="! aperto" />
                <x-icona name="x" class="size-6" x-show="aperto" x-cloak />
            </button>
        </div>
    </div>

    <div id="menu-mobile" class="border-t border-white/15 bg-fondo lg:hidden"
         x-show="aperto" x-cloak
         x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:leave="transition duration-100 ease-in" x-transition:leave-end="opacity-0 -translate-y-2">
        <nav aria-label="Menu mobile" class="contenitore py-4">
            <ul class="divide-y divide-white/10">
                @foreach($menuItems as $item)
                    <li>
                        <a href="{{ $item['url'] }}" x-on:click="aperto = false" class="flex min-h-12 items-center text-lg font-bold">{{ $item['label'] }}</a>
                    </li>
                @endforeach
            </ul>
            @if($hasCta)
                <a href="{{ $settings->header_cta_url }}" x-on:click="aperto = false" class="btn-segnale mt-4 w-full">{{ $settings->header_cta_label }}</a>
            @endif
        </nav>
    </div>
</header>
