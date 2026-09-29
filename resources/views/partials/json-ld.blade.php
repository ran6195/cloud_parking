@php
    $parking = app(\App\Settings\ParkingSettings::class);
    $organization = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'ParkingFacility',
        'name' => $settings->site_name,
        'legalName' => $settings->company_name,
        'vatID' => $settings->vat_number,
        'description' => $settings->default_meta_description,
        'url' => url('/'),
        'telephone' => $settings->phone,
        'email' => $settings->email,
        'hasMap' => $parking->maps_url,
        'address' => $settings->operational_address ? [
            '@type' => 'PostalAddress',
            'streetAddress' => $settings->operational_address,
            'addressCountry' => 'IT',
        ] : null,
        'sameAs' => array_values(array_filter([
            $settings->facebook_url,
            $settings->instagram_url,
            $settings->linkedin_url,
        ])) ?: null,
    ]);
    $organizationJson = json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $breadcrumbItems = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => $settings->site_name, 'item' => url('/')],
    ];

    if ($page && ! $page->is_homepage) {
        $breadcrumbItems[] = [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => $page->title,
            'item' => url('/'.$page->slug),
        ];
    }

    $breadcrumbJson = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $breadcrumbItems,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $faqItems = collect($page->blocks ?? [])
        ->where('type', 'faq')
        ->flatMap(fn ($block) => $block['data']['items'] ?? []);

    $faqJson = $faqItems->isNotEmpty()
        ? json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqItems->map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['question'] ?? '',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => \App\Support\SiteText::fill($item['answer'] ?? ''),
                ],
            ])->values()->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        : null;
@endphp

<script type="application/ld+json">{!! $organizationJson !!}</script>

<script type="application/ld+json">{!! $breadcrumbJson !!}</script>

@if($faqJson)
    <script type="application/ld+json">{!! $faqJson !!}</script>
@endif
