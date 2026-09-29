@php
    use App\Support\SiteText;

    $footerLinks = $settings->footer_links ?? [];
@endphp
<footer class="bg-fondo text-testo-chiaro">
    <div class="contenitore grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr]">
        <div>
            <x-logo :name="$settings->site_name" class="text-carta" />
            @if($settings->site_tagline)
                <p class="mt-4 max-w-sm">{{ $settings->site_tagline }}</p>
            @endif
        </div>

        <div>
            <h2 class="font-display text-lg font-bold text-carta">Contatti</h2>
            <ul class="mt-3 space-y-1">
                @if($settings->operational_address)
                    <li>{{ $settings->operational_address }}</li>
                @endif
                @if($settings->phone)
                    <li><a href="{{ SiteText::telHref($settings->phone) }}" class="inline-flex min-h-11 items-center hover:text-carta">{{ $settings->phone }}</a></li>
                @endif
                @if($settings->email)
                    <li><a href="mailto:{{ $settings->email }}" class="inline-flex min-h-11 items-center break-all hover:text-carta">{{ $settings->email }}</a></li>
                @endif
            </ul>
        </div>

        @if(! empty($footerLinks))
            <nav aria-label="Informazioni legali">
                <h2 class="font-display text-lg font-bold text-carta">Informazioni</h2>
                <ul class="mt-3">
                    @foreach($footerLinks as $link)
                        <li><a href="{{ $link['url'] }}" class="inline-flex min-h-11 items-center hover:text-carta">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>

    <div class="border-t border-white/15">
        <p class="contenitore py-5 text-sm">
            &copy; {{ now()->year }} {{ $settings->company_name ?: $settings->site_name }}
            @if($settings->vat_number) · P.IVA {{ $settings->vat_number }} @endif
            @if($settings->legal_address) · {{ $settings->legal_address }} @endif
        </p>
    </div>
</footer>
