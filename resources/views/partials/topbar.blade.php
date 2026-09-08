@if($settings->topbar_enabled)
    <div style="background-color: {{ $settings->topbar_bg_color ?: '#0f172a' }}; color: {{ $settings->topbar_text_color ?: '#f8fafc' }}; min-height: {{ $settings->topbar_height ?: 40 }}px;"
         class="flex items-center text-xs sm:text-sm">
        <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-2 px-6">
            <div class="flex flex-wrap items-center gap-4">
                @if($settings->topbar_show_hours && $settings->opening_hours)
                    <span class="opacity-90">{{ $settings->opening_hours }}</span>
                @endif

                @if($settings->topbar_show_address && $settings->operational_address)
                    <span class="hidden opacity-90 sm:inline">{{ $settings->operational_address }}</span>
                @endif
            </div>

            <div class="flex items-center gap-4">
                @if($settings->topbar_show_whatsapp && $settings->whatsapp)
                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $settings->whatsapp) }}" aria-label="WhatsApp" class="opacity-90 hover:opacity-100">
                        <x-icon-whatsapp class="h-4 w-4" />
                    </a>
                @endif

                @if($settings->topbar_show_phone && $settings->phone)
                    <a href="tel:{{ $settings->phone }}" class="font-medium opacity-90 hover:opacity-100">
                        {{ $settings->phone }}
                    </a>
                @endif
            </div>
        </div>
    </div>
@endif
