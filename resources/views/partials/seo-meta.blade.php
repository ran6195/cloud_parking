@php
    $title = $page ? $page->getMetaTitleForSeo() : $settings->site_name;
    $description = $page ? $page->getMetaDescriptionForSeo() : $settings->default_meta_description;
    $description ??= $settings->default_meta_description;
    $canonical = $page?->canonical_url ?: url($page && ! $page->is_homepage ? '/'.$page->slug : '/');
    $ogTitle = $page?->og_title ?: $title;
    $ogDescription = $page?->og_description ?: $description;
    $ogImageMedia = $page?->getFirstMedia('og_image') ?? $page?->getFirstMedia('cover');
    $ogImage = $ogImageMedia?->getUrl() ?: $settings->default_og_image;
    $robots = collect([
        ($page && ! $page->robots_index) ? 'noindex' : 'index',
        ($page && ! $page->robots_follow) ? 'nofollow' : 'follow',
    ])->implode(', ');
@endphp

<title>{{ $title }}{{ $page && ! $page->is_homepage ? ' · '.$settings->site_name : '' }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonical }}">

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $settings->site_name }}">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDescription }}">
<meta property="og:url" content="{{ $canonical }}">
@if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
@endif

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $ogDescription }}">

@if($settings->search_console_verification)
    <meta name="google-site-verification" content="{{ $settings->search_console_verification }}">
@endif

@if($settings->google_tag_manager_id)
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ $settings->google_tag_manager_id }}');
    </script>
@endif

@if($settings->google_analytics_id)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $settings->google_analytics_id }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $settings->google_analytics_id }}');
    </script>
@endif
