<x-section :data="$data" default-background="terra">
    <div class="mx-auto max-w-2xl rounded-modulo bg-carta p-6 sm:p-10">
        @if(! empty($data['heading']))
            <h2 class="titolo-sezione">{{ $data['heading'] }}</h2>
        @endif
        @if(! empty($data['subheading']))
            <p class="mt-3 text-lg text-testo-soft">{{ \App\Support\SiteText::fill($data['subheading']) }}</p>
        @endif

        @if(session('lead_sent'))
            <p class="mt-6 flex items-center gap-3 rounded-controllo border border-bordo bg-terra p-4 font-bold" role="status">
                <x-icona name="check" class="size-6" />
                Grazie, la tua richiesta è stata inviata. Ti rispondiamo al più presto.
            </p>
        @else
            <form method="POST" action="{{ route('leads.store') }}" class="mt-8 space-y-5">
                @csrf
                <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                <input type="hidden" name="source_url" value="{{ url()->current() }}">

                @foreach([
                    ['name', 'Nome', 'text', true, 'name'],
                    ['email', 'Email', 'email', true, 'email'],
                    ['phone', 'Telefono', 'tel', false, 'tel'],
                ] as [$field, $label, $type, $required, $autocomplete])
                    <div>
                        <label for="lead-{{ $field }}" class="block font-bold">{{ $label }} @unless($required)<span class="font-normal text-testo-soft">(facoltativo)</span>@endunless</label>
                        <input id="lead-{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" autocomplete="{{ $autocomplete }}"
                               @if($required) required @endif class="campo mt-2"
                               @error($field) aria-invalid="true" aria-describedby="lead-{{ $field }}-errore" @enderror>
                        @error($field)
                            <p id="lead-{{ $field }}-errore" class="mt-2 text-sm font-bold text-errore">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <div>
                    <label for="lead-message" class="block font-bold">Messaggio <span class="font-normal text-testo-soft">(facoltativo)</span></label>
                    <textarea id="lead-message" name="message" rows="4"
                              class="campo mt-2"
                              @error('message') aria-invalid="true" aria-describedby="lead-message-errore" @enderror>{{ old('message') }}</textarea>
                    @error('message')
                        <p id="lead-message-errore" class="mt-2 text-sm font-bold text-errore">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn-primario w-full sm:w-auto">
                    {{ $data['submit_label'] ?? 'Invia richiesta' }}
                </button>
            </form>
        @endif
    </div>
</x-section>
