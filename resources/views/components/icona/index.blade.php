@props(['name'])
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" {{ $attributes->merge(['class' => 'size-6 shrink-0', 'stroke-width' => '1.8']) }}>
    @includeIf('components.icona.paths.'.$name)
</svg>
