@php
    /** @var \App\Models\Page|null $page */
    $settings = app(\App\Settings\GeneralSettings::class);
    $menuPages = \App\Models\Page::query()->published()->inMenu()->get();
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @include('partials.seo-meta', ['page' => $page ?? null, 'settings' => $settings])
    @include('partials.json-ld', ['page' => $page ?? null, 'settings' => $settings])

    @php
        $fontUrls = collect([$settings->header_font, $page->font ?? null])
            ->unique()
            ->map(fn ($font) => \App\Support\GoogleFonts::googleFontsUrl($font))
            ->filter()
            ->unique();
    @endphp
    @if($fontUrls->isNotEmpty())
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        @foreach($fontUrls as $fontUrl)
            <link rel="stylesheet" href="{{ $fontUrl }}">
        @endforeach
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-slate-800 antialiased">
    @include('partials.topbar', ['settings' => $settings])
    @include('partials.header', ['settings' => $settings, 'menuPages' => $menuPages])

    <main style="font-family: {{ \App\Support\GoogleFonts::cssFamily($page->font ?? null) }};">
        @yield('content')
    </main>

    @include('partials.footer', ['settings' => $settings, 'menuPages' => $menuPages])
</body>
</html>
