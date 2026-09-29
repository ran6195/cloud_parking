@php
    /** @var \App\Models\Page|null $page */
    $settings = app(\App\Settings\GeneralSettings::class);
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f3b47">

    @include('partials.seo-meta', ['page' => $page ?? null, 'settings' => $settings])
    @include('partials.json-ld', ['page' => $page ?? null, 'settings' => $settings])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400&family=Bricolage+Grotesque:wght@700;800&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen antialiased">
    <a href="#contenuto" class="sr-only z-50 rounded-controllo bg-segnale px-4 py-3 font-bold text-fondo focus:not-sr-only focus:fixed focus:top-3 focus:left-3">
        Vai al contenuto
    </a>

    @include('partials.header', ['settings' => $settings])

    <main id="contenuto">
        @yield('content')
    </main>

    @include('partials.footer', ['settings' => $settings])

    @livewireScripts
</body>
</html>
