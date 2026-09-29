@props(['id', 'index', 'question', 'answer'])
<div class="border-b border-bordo">
    <h3>
        <button type="button"
                id="{{ $id }}-domanda"
                aria-controls="{{ $id }}-risposta"
                :aria-expanded="aperta === {{ $index }} ? 'true' : 'false'"
                x-on:click="aperta = aperta === {{ $index }} ? null : {{ $index }}"
                class="flex min-h-11 w-full items-center justify-between gap-4 py-5 text-left font-display text-lg leading-snug font-bold">
            <span>{{ $question }}</span>
            <x-icona name="chevron-down" class="size-6 transition-transform" x-bind:class="aperta === {{ $index }} && 'rotate-180'" />
        </button>
    </h3>
    <div id="{{ $id }}-risposta" role="region" aria-labelledby="{{ $id }}-domanda"
         x-show="aperta === {{ $index }}" x-collapse x-cloak>
        <p class="pb-6 text-testo-soft">{{ $answer }}</p>
    </div>
</div>
