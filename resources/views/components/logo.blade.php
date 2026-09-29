@props(['name'])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3 font-display text-xl font-extrabold tracking-[-0.5px]']) }}>
    <span class="grid size-10 place-items-center rounded-[8px] bg-segnale text-2xl leading-none text-fondo" aria-hidden="true">P</span>
    <span>{{ $name }}</span>
</span>
