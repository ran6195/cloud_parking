@props(['number', 'title', 'description' => null])
<li class="flex gap-4 rounded-card bg-carta p-5 sm:p-6 lg:flex-col lg:gap-5">
    <span class="grid size-11 shrink-0 place-items-center rounded-full bg-segnale font-display text-xl font-extrabold text-fondo" aria-hidden="true">{{ $number }}</span>
    <div>
        <h3 class="font-display text-xl leading-tight font-bold">{{ $title }}</h3>
        @if($description)
            <p class="mt-2 text-testo-soft">{{ $description }}</p>
        @endif
    </div>
</li>
