@php
    $headerBg = $settings->header_bg_color ?: '#ffffff';
    $headerText = $settings->header_text_color ?: '#0f172a';
    $headerFontFamily = \App\Support\HeaderFonts::cssFamily($settings->header_font);
    $logoUrl = $settings->header_logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($settings->header_logo) : null;
@endphp
<header style="background-color: {{ $headerBg }}; color: {{ $headerText }}; font-family: {{ $headerFontFamily }};" class="border-b border-black/10">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-6 py-4">
        <a href="{{ url('/') }}" class="flex items-center gap-2 text-lg font-semibold" style="color: {{ $headerText }};">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $settings->site_name }}" class="h-8 w-auto">
            @else
                {{ $settings->site_name }}
            @endif
        </a>

        <nav class="flex items-center gap-6 text-sm font-medium">
            @foreach($menuPages as $menuPage)
                <a href="{{ $menuPage->is_homepage ? url('/') : url('/'.$menuPage->slug) }}" class="opacity-80 hover:opacity-100">
                    {{ $menuPage->title }}
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-4 text-sm">
            @if($settings->header_show_phone && $settings->phone)
                <a href="tel:{{ $settings->phone }}" class="hidden font-medium opacity-90 hover:opacity-100 sm:inline">
                    {{ $settings->phone }}
                </a>
            @endif

            @if($settings->header_show_hours && $settings->opening_hours)
                <span class="hidden opacity-70 md:inline">{{ $settings->opening_hours }}</span>
            @endif

            @if($settings->header_cta_label && $settings->header_cta_url)
                <a href="{{ $settings->header_cta_url }}"
                   class="rounded-md px-4 py-2 text-sm font-semibold"
                   style="background-color: {{ $headerText }}; color: {{ $headerBg }};">
                    {{ $settings->header_cta_label }}
                </a>
            @endif
        </div>
    </div>
</header>
