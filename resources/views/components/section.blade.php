@props(['data' => [], 'defaultBackground' => 'terra', 'defaultAnchor' => null, 'compact' => false])
@php
    $background = ($data['background'] ?? $defaultBackground) === 'carta' ? 'bg-carta' : 'bg-terra';
    $anchor = array_key_exists('anchor', $data) ? $data['anchor'] : $defaultAnchor;
@endphp
<section @if($anchor) id="{{ $anchor }}" @endif {{ $attributes->merge(['class' => $background.($compact ? ' py-12 sm:py-16' : ' py-16 sm:py-20 lg:py-24')]) }}>
    <div class="contenitore">
        {{ $slot }}
    </div>
</section>
