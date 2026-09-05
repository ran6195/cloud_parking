<header class="border-b border-slate-200">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
        <a href="{{ url('/') }}" class="text-lg font-semibold text-slate-900">
            {{ $settings->site_name }}
        </a>

        <nav class="flex items-center gap-6 text-sm font-medium text-slate-600">
            @foreach($menuPages as $menuPage)
                <a href="{{ $menuPage->is_homepage ? url('/') : url('/'.$menuPage->slug) }}" class="hover:text-slate-900">
                    {{ $menuPage->title }}
                </a>
            @endforeach
        </nav>
    </div>
</header>
