@php
    use App\Support\SiteText;

    $money = fn (?array $amount) => $amount === null ? '' : SiteText::euro($amount['amount_cents'] / 100);
    $when = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->setTimezone('Europe/Rome')->locale('it')->isoFormat('ddd D MMM, HH:mm') : '';
    $field = fn (string $name) => $errors->has($name) ? 'aria-invalid=true aria-describedby=bf-'.$name.'-errore' : '';
@endphp
<div class="rounded-modulo bg-carta p-6 text-testo shadow-modulo sm:p-8">
    @if($unavailable)
        <h2 class="font-display text-2xl leading-tight font-extrabold tracking-[-0.5px]">Prenota con una telefonata</h2>
        <p class="mt-2 text-testo-soft">Il sistema di prenotazione online non è raggiungibile in questo momento.</p>
        @if($general->phone)
            <a href="{{ SiteText::telHref($general->phone) }}" class="btn-primario mt-6 w-full">
                <x-icona name="phone" class="size-5" />
                Chiama {{ $general->phone }}
            </a>
        @endif

    @elseif($step === 'done' && $reservation)
        <div aria-live="polite">
            <h2 class="font-display text-2xl leading-tight font-extrabold tracking-[-0.5px]">
                @if($reservation['status'] === 'pending_payment') Ultimo passo: il pagamento @else Prenotazione confermata @endif
            </h2>
            <p class="mt-1 text-testo-soft">Prenotazione n. <strong class="text-testo">{{ $reservation['number'] }}</strong> · {{ $when($reservation['stay']['start']) }} → {{ $when($reservation['stay']['end']) }}</p>

            <div class="mt-5 rounded-card border border-bordo bg-terra p-5">
                <p class="text-testo-soft">Totale</p>
                <p class="mt-1 font-display text-4xl font-extrabold tracking-[-1px]">{{ $money($reservation['total']) }}</p>

                @if($reservation['status'] === 'pending_payment')
                    <p class="mt-3 text-sm text-testo-soft">Il posto è tenuto per te fino alle {{ \Illuminate\Support\Carbon::parse($reservation['payment']['due_by'])->setTimezone('Europe/Rome')->format('H:i') }}.</p>
                    @error('form') <p class="mt-3 text-sm font-bold text-errore">{{ $message }}</p> @enderror
                    @if($paypalButtons)
                        <div id="bf-paypal" class="mt-4" wire:ignore></div>
                        @script
                        <script>
                            const load = () => window.paypal
                                ? Promise.resolve()
                                : new Promise((resolve) => {
                                    const s = document.createElement('script');
                                    s.src = 'https://www.paypal.com/sdk/js?currency=EUR&client-id=' + encodeURIComponent(@js($paypalClientId));
                                    s.onload = resolve;
                                    document.head.appendChild(s);
                                });
                            load().then(() => window.paypal.Buttons({
                                createOrder: () => $wire.creaOrdinePaypal(),
                                onApprove: (data) => $wire.incassaPaypal(data.orderID),
                            }).render('#bf-paypal'));
                        </script>
                        @endscript
                    @else
                        <button type="button" wire:click="pagaSimulato" class="btn-primario mt-4 w-full" wire:loading.attr="disabled" wire:target="pagaSimulato">
                            Paga ora {{ $money($reservation['total']) }}
                        </button>
                        <p class="mt-2 text-xs text-testo-soft">Pagamento di prova: nessun addebito.</p>
                    @endif
                @elseif($reservation['payment']['status'] === 'paid')
                    <p class="mt-3 flex items-center gap-2"><x-icona name="check" class="size-5 text-fondo" /> Pagamento ricevuto. Ti abbiamo mandato la conferma per email.</p>
                @else
                    <p class="mt-3 flex items-center gap-2"><x-icona name="check" class="size-5 text-fondo" /> Paghi in parcheggio alla consegna dell'auto. Ti abbiamo mandato la conferma per email.</p>
                @endif
            </div>
            <p class="mt-4 text-sm text-testo-soft">Cancellazione gratuita fino al {{ $when($reservation['cancellation']['free_until']) }}, con il link nell'email.</p>
        </div>

    @elseif($step === 'details' && $quote)
        <h2 class="font-display text-2xl leading-tight font-extrabold tracking-[-0.5px]">I tuoi dati</h2>
        <p class="mt-1 text-testo-soft">{{ $when($quote['stay']['start']) }} → {{ $when($quote['stay']['end']) }} · <strong class="text-testo">{{ $money($quote['total']) }}</strong>
            <button type="button" wire:click="indietro" class="ml-1 underline">modifica</button></p>

        @if($notice)<p class="mt-4 rounded-card border border-bordo bg-terra p-3 text-sm font-bold" role="status">{{ $notice }}</p>@endif

        <form wire:submit="prenota" class="mt-6 grid gap-4 sm:grid-cols-2" novalidate>
            @foreach([
                ['nome', 'Nome', 'text', 'given-name'], ['cognome', 'Cognome', 'text', 'family-name'],
                ['email', 'Email', 'email', 'email'], ['telefono', 'Telefono', 'tel', 'tel'],
                ['targa', 'Targa', 'text', 'off'], ['marca', 'Marca (facoltativa)', 'text', 'off'],
                ['voloAndata', 'Volo di andata (facoltativo)', 'text', 'off'], ['voloRitorno', 'Volo di ritorno (facoltativo)', 'text', 'off'],
            ] as [$name, $label, $type, $autocomplete])
                <div>
                    <label for="bf-{{ $name }}" class="block font-bold">{{ $label }}</label>
                    <input id="bf-{{ $name }}" type="{{ $type }}" wire:model="{{ $name }}" autocomplete="{{ $autocomplete }}" class="campo mt-2" {!! $field($name) !!}>
                    @error($name) <p id="bf-{{ $name }}-errore" class="mt-2 text-sm font-bold text-errore">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <div class="sm:col-span-2">
                <label for="bf-note" class="block font-bold">Note (facoltative)</label>
                <textarea id="bf-note" wire:model="note" rows="2" class="campo mt-2"></textarea>
            </div>
            <div class="sm:col-span-2 space-y-2">
                <label class="flex items-start gap-2"><input type="checkbox" wire:model="privacy" class="mt-1" {!! $field('privacy') !!}> <span>Accetto il trattamento dei dati per gestire la prenotazione.</span></label>
                @error('privacy') <p id="bf-privacy-errore" class="text-sm font-bold text-errore">{{ $message }}</p> @enderror
                <label class="flex items-start gap-2"><input type="checkbox" wire:model="marketing" class="mt-1"> <span class="text-testo-soft">Voglio ricevere offerte via email (facoltativo).</span></label>
            </div>
            @error('form') <p class="sm:col-span-2 text-sm font-bold text-errore">{{ $message }}</p> @enderror
            <button type="submit" class="btn-primario sm:col-span-2" wire:loading.attr="disabled" wire:target="prenota">
                <span wire:loading.remove wire:target="prenota">{{ $quote['payment_method'] === 'online' ? 'Prenota e paga' : 'Conferma la prenotazione' }} · {{ $money($quote['total']) }}</span>
                <span wire:loading wire:target="prenota">Prenotazione in corso…</span>
            </button>
        </form>

    @else
        <h2 class="font-display text-2xl leading-tight font-extrabold tracking-[-0.5px]">Quanto costa il tuo viaggio?</h2>
        <p class="mt-1 text-testo-soft">Inserisci le date, il prezzo lo vedi subito.</p>

        @if($notice)<p class="mt-4 rounded-card border border-bordo bg-terra p-3 text-sm font-bold" role="status">{{ $notice }}</p>@endif

        <form wire:submit="calcola" class="mt-6 space-y-5" novalidate>
            <div>
                <label for="bf-partenza" class="block font-bold">Lasci l'auto il</label>
                <input id="bf-partenza" type="datetime-local" wire:model.blur="partenza" min="{{ $minDate }}" class="campo mt-2" {!! $field('partenza') !!}>
                @error('partenza') <p id="bf-partenza-errore" class="mt-2 text-sm font-bold text-errore">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="bf-rientro" class="block font-bold">Rientri il <span class="font-normal text-testo-soft">(atterraggio alle)</span></label>
                <input id="bf-rientro" type="datetime-local" wire:model.blur="rientro" min="{{ $partenza ?: $minDate }}" class="campo mt-2" {!! $field('rientro') !!}>
                @error('rientro') <p id="bf-rientro-errore" class="mt-2 text-sm font-bold text-errore">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="bf-persone" class="block font-bold">Persone in navetta</label>
                    <select id="bf-persone" wire:model.live="persone" class="campo mt-2" {!! $field('persone') !!}>
                        @foreach(\App\Livewire\BookingForm::PEOPLE_OPTIONS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('persone') <p id="bf-persone-errore" class="mt-2 text-sm font-bold text-errore">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="bf-pagamento" class="block font-bold">Pagamento</label>
                    <select id="bf-pagamento" wire:model.live="pagamento" class="campo mt-2">
                        <option value="on_site">In parcheggio</option>
                        <option value="online">Online, subito</option>
                    </select>
                </div>
            </div>

            @if($extras)
                <fieldset>
                    <legend class="font-bold">Servizi in più</legend>
                    <div class="mt-2 space-y-2">
                        @foreach($extras as $extra)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" value="{{ $extra['id'] }}" wire:model.live="servizi">
                                <span>{{ $extra['name'] }}</span>
                                <span class="text-sm text-testo-soft">
                                    @if($extra['pricing'] === 'percentage_of_parking') +{{ $extra['percentage'] }}% @elseif($extra['price']) + {{ $money($extra['price']) }} @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('servizi') <p class="mt-2 text-sm font-bold text-errore">{{ $message }}</p> @enderror
                </fieldset>
            @endif

            <div>
                <label for="bf-codice" class="block font-bold">Codice sconto <span class="font-normal text-testo-soft">(facoltativo)</span></label>
                <input id="bf-codice" type="text" wire:model.blur="codice" class="campo mt-2" autocomplete="off">
                @error('codice') <p class="mt-2 text-sm font-bold text-errore">{{ $message }}</p> @enderror
            </div>

            @error('form') <p class="text-sm font-bold text-errore">{{ $message }}</p> @enderror

            <div aria-live="polite">
                @if($quote)
                    <div class="rounded-card border border-bordo bg-terra p-5">
                        <p class="text-testo-soft">
                            {{ $quote['stay']['billable_days'] }} {{ $quote['stay']['billable_days'] === 1 ? 'giorno' : 'giorni' }} di sosta, navetta inclusa
                        </p>
                        <ul class="mt-3 space-y-1 text-sm">
                            @foreach($quote['lines'] as $line)
                                <li class="flex justify-between gap-4"><span>{{ $line['description'] }}</span><span>{{ $money($line['total']) }}</span></li>
                            @endforeach
                            @if($quote['discount'])
                                <li class="flex justify-between gap-4">
                                    <span>Codice {{ $quote['discount']['code'] }}</span>
                                    <span>{{ $quote['discount']['applied'] ? '− '.$money($quote['discount']['amount']) : ['not_found' => 'non valido', 'expired' => 'scaduto', 'exhausted' => 'esaurito'][$quote['discount']['reason']] ?? 'non valido' }}</span>
                                </li>
                            @endif
                        </ul>
                        <p class="mt-3 font-display text-4xl font-extrabold tracking-[-1px]">{{ $money($quote['total']) }}</p>
                        @if($quote['bookable'])
                            <button type="button" wire:click="avanti" class="btn-primario mt-4 w-full">
                                Prenota a questo prezzo
                                <x-icona name="arrow-right" class="size-5" />
                            </button>
                        @else
                            <p class="mt-4 font-bold text-errore">Per queste date non ci sono più posti{{ ($quote['availability']['parking']['available'] ?? true) ? ' per i servizi scelti' : '' }}.</p>
                            @if($general->phone)
                                <a href="{{ SiteText::telHref($general->phone) }}" class="btn-outline mt-3 w-full">Chiamaci per verificare</a>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            @if($quote === null)
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
    @endif
</div>
