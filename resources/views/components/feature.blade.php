@props(['icon' => null, 'title', 'description' => null])
<li class="flex gap-4">
    <span class="grid size-12 shrink-0 place-items-center rounded-controllo border border-bordo bg-carta text-fondo">
        <x-icona :name="$icon ?: 'check'" class="size-6" />
    </span>
    <div>
        <h3 class="font-display text-lg leading-tight font-bold">{{ $title }}</h3>
        @if($description)
            <p class="mt-1 text-testo-soft">{{ $description }}</p>
        @endif
    </div>
</li>
