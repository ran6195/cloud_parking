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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-slate-800 antialiased">
    @include('partials.header', ['settings' => $settings, 'menuPages' => $menuPages])

    <main>
        @yield('content')
    </main>

    @include('partials.footer', ['settings' => $settings, 'menuPages' => $menuPages])
</body>
</html>
