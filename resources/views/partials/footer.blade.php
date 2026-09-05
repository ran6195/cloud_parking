<footer class="border-t border-slate-200 bg-slate-50">
    <div class="mx-auto grid max-w-6xl gap-8 px-6 py-12 sm:grid-cols-3">
        <div>
            <p class="font-semibold text-slate-900">{{ $settings->site_name }}</p>
            @if($settings->site_tagline)
                <p class="mt-1 text-sm text-slate-500">{{ $settings->site_tagline }}</p>
            @endif
            @if($settings->company_name)
                <p class="mt-4 text-xs text-slate-400">
                    {{ $settings->company_name }}
                    @if($settings->vat_number) &middot; P.IVA {{ $settings->vat_number }} @endif
                </p>
            @endif
        </div>

        <div class="text-sm text-slate-600">
            <p class="font-semibold text-slate-900">Contatti</p>
            @if($settings->phone)<p class="mt-2"><a href="tel:{{ $settings->phone }}" class="hover:text-slate-900">{{ $settings->phone }}</a></p>@endif
            @if($settings->whatsapp)<p><a href="https://wa.me/{{ preg_replace('/\D/', '', $settings->whatsapp) }}" class="hover:text-slate-900">WhatsApp</a></p>@endif
            @if($settings->email)<p><a href="mailto:{{ $settings->email }}" class="hover:text-slate-900">{{ $settings->email }}</a></p>@endif
            @if($settings->operational_address)<p class="mt-2">{{ $settings->operational_address }}</p>@endif
            @if($settings->opening_hours)<p class="mt-2">{{ $settings->opening_hours }}</p>@endif
        </div>

        <div class="text-sm text-slate-600">
            <p class="font-semibold text-slate-900">Link</p>
            <div class="mt-2 flex flex-col gap-1">
                @foreach($menuPages as $menuPage)
                    <a href="{{ $menuPage->is_homepage ? url('/') : url('/'.$menuPage->slug) }}" class="hover:text-slate-900">
                        {{ $menuPage->title }}
                    </a>
                @endforeach
            </div>
            <div class="mt-4 flex gap-3">
                @if($settings->facebook_url)<a href="{{ $settings->facebook_url }}" class="hover:text-slate-900">Facebook</a>@endif
                @if($settings->instagram_url)<a href="{{ $settings->instagram_url }}" class="hover:text-slate-900">Instagram</a>@endif
                @if($settings->linkedin_url)<a href="{{ $settings->linkedin_url }}" class="hover:text-slate-900">LinkedIn</a>@endif
            </div>
        </div>
    </div>

    <div class="border-t border-slate-200 px-6 py-4 text-center text-xs text-slate-400">
        &copy; {{ now()->year }} {{ $settings->site_name }}
    </div>
</footer>
