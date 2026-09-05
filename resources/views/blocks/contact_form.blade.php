<section class="mx-auto max-w-2xl px-6 py-16">
    @if(! empty($data['heading']))
        <h2 class="text-2xl font-bold text-slate-900">{{ $data['heading'] }}</h2>
    @endif
    @if(! empty($data['subheading']))
        <p class="mt-2 text-slate-600">{{ $data['subheading'] }}</p>
    @endif

    @if(session('lead_sent'))
        <div class="mt-6 rounded-md bg-green-50 p-4 text-sm text-green-800">
            Grazie, la tua richiesta &egrave; stata inviata con successo.
        </div>
    @else
        <form method="POST" action="{{ route('leads.store') }}" class="mt-6 space-y-4">
            @csrf
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            <input type="hidden" name="source_url" value="{{ url()->current() }}">

            <div>
                <label class="block text-sm font-medium text-slate-700">Nome</label>
                <input type="text" name="name" required value="{{ old('name') }}"
                       class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Email</label>
                <input type="email" name="email" required value="{{ old('email') }}"
                       class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Telefono</label>
                <input type="tel" name="phone" value="{{ old('phone') }}"
                       class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Messaggio</label>
                <textarea name="message" rows="4"
                          class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">{{ old('message') }}</textarea>
            </div>

            @if($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    <ul class="list-inside list-disc">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <button type="submit" class="rounded-md bg-slate-900 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-700">
                {{ $data['submit_label'] ?? 'Invia richiesta' }}
            </button>
        </form>
    @endif
</section>
