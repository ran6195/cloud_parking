<div class="rounded-modulo bg-carta p-6 text-testo shadow-modulo sm:p-8">
    <h2 class="font-display text-2xl leading-tight font-extrabold tracking-[-0.5px]">Quanto costa il tuo viaggio?</h2>
    <p class="mt-1 text-testo-soft">Inserisci le date, il prezzo lo vedi subito.</p>

    <form wire:submit="calcola" class="mt-6 space-y-5" novalidate>
        <div>
            <label for="bf-partenza" class="block font-bold">Lasci l'auto il</label>
            <input id="bf-partenza" type="datetime-local" wire:model.blur="partenza" min="{{ $minDate }}"
                   class="campo mt-2"
                   @error('partenza') aria-invalid="true" aria-describedby="bf-partenza-errore" @enderror>
            @error('partenza')
                <p id="bf-partenza-errore" class="mt-2 text-sm font-bold text-errore">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="bf-rientro" class="block font-bold">Rientri il <span class="font-normal text-testo-soft">(atterraggio alle)</span></label>
            <input id="bf-rientro" type="datetime-local" wire:model.blur="rientro" min="{{ $partenza ?: $minDate }}"
                   class="campo mt-2"
                   @error('rientro') aria-invalid="true" aria-describedby="bf-rientro-errore" @enderror>
            @error('rientro')
                <p id="bf-rientro-errore" class="mt-2 text-sm font-bold text-errore">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="bf-persone" class="block font-bold">Persone in navetta</label>
            <select id="bf-persone" wire:model.live="persone" class="campo mt-2"
                    @error('persone') aria-invalid="true" aria-describedby="bf-persone-errore" @enderror>
                @foreach(\App\Livewire\BookingForm::PEOPLE_OPTIONS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('persone')
                <p id="bf-persone-errore" class="mt-2 text-sm font-bold text-errore">{{ $message }}</p>
            @enderror
        </div>

        <div aria-live="polite">
            @if($price !== null)
                <div class="rounded-card border border-bordo bg-terra p-5">
                    <p class="text-testo-soft">
                        {{ $days }} {{ $days === 1 ? 'giorno' : 'giorni' }} di sosta, navetta inclusa
                    </p>
                    <p class="mt-1 font-display text-4xl font-extrabold tracking-[-1px]">{{ \App\Support\SiteText::euro($price) }}</p>
                    @if($bookingUrl)
                        <a href="{{ $bookingUrl }}" class="btn-primario mt-4 w-full">
                            Prenota a questo prezzo
                            <x-icona name="arrow-right" class="size-5" />
                        </a>
                    @elseif($general->phone)
                        <a href="{{ \App\Support\SiteText::telHref($general->phone) }}" class="btn-primario mt-4 w-full">
                            <x-icona name="phone" class="size-5" />
                            Chiama per prenotare
                        </a>
                    @endif
                </div>
            @endif
        </div>

        @if($price === null)
            <button type="submit" class="btn-primario w-full" wire:loading.attr="disabled" wire:target="calcola">
                <span wire:loading.remove wire:target="calcola">Calcola il prezzo</span>
                <span wire:loading wire:target="calcola">Calcolo in corso…</span>
            </button>
        @endif
    </form>

    <p class="mt-5 flex items-center gap-2 text-sm text-testo-soft">
        <x-icona name="check" class="size-5 text-fondo" />
        Cancellazione gratuita fino a {{ $parking->free_cancellation_hours }} ore prima della partenza
    </p>
</div>
