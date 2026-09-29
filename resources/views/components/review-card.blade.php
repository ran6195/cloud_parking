@props(['quote', 'author', 'role' => null])
<figure class="flex flex-col rounded-card bg-carta p-6 sm:p-7">
    <div class="flex gap-0.5 text-fondo" aria-hidden="true">
        @for($i = 0; $i < 5; $i++)
            <x-icona name="star" class="size-5 fill-segnale" />
        @endfor
    </div>
    <blockquote class="mt-4 flex-1 text-lg leading-relaxed">
        <p>&ldquo;{{ $quote }}&rdquo;</p>
    </blockquote>
    <figcaption class="mt-5 border-t border-bordo pt-4">
        <span class="block font-bold">{{ $author }}</span>
        @if($role)
            <span class="block text-sm text-testo-soft">{{ $role }}</span>
        @endif
    </figcaption>
</figure>
