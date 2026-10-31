<?php

/*
 * Il motore e il CMS convivono nello stesso repo ma non si conoscono:
 * il CMS parla con il motore solo via HTTP (/api/v1).
 */

const CMS_NAMESPACES = [
    'App\Filament',
    'App\Http',
    'App\Livewire',
    'App\Mail',
    'App\Models',
    'App\Providers',
    'App\Services',
    'App\Settings',
    'App\Support',
];

arch('il motore non usa classi del CMS')
    ->expect('App\Engine')
    ->not->toUse(CMS_NAMESPACES);

arch('il CMS non usa classi del motore')
    ->expect(CMS_NAMESPACES)
    ->not->toUse('App\Engine');

arch('il dominio del motore non dipende da HTTP, database o facade')
    ->expect('App\Engine\Domain')
    ->not->toUse([
        'Illuminate\Http',
        'Illuminate\Database',
        'Illuminate\Support\Facades',
        'Illuminate\Routing',
    ]);

arch('il motore è privo di codice di debug')
    ->expect('App\Engine')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);
