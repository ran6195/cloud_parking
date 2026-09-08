<?php

namespace App\Support;

class GoogleFonts
{
    /**
     * @var array<string, array{label: string, family: string, google: ?string}>
     */
    protected static array $fonts = [
        'default' => [
            'label' => 'Predefinito (Instrument Sans)',
            'family' => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
            'google' => null,
        ],

        // Sans-serif
        'inter' => ['label' => 'Inter', 'family' => "'Inter', ui-sans-serif, system-ui, sans-serif", 'google' => 'Inter:wght@400;500;600;700'],
        'roboto' => ['label' => 'Roboto', 'family' => "'Roboto', ui-sans-serif, system-ui, sans-serif", 'google' => 'Roboto:wght@400;500;700'],
        'open_sans' => ['label' => 'Open Sans', 'family' => "'Open Sans', ui-sans-serif, system-ui, sans-serif", 'google' => 'Open+Sans:wght@400;500;600;700'],
        'lato' => ['label' => 'Lato', 'family' => "'Lato', ui-sans-serif, system-ui, sans-serif", 'google' => 'Lato:wght@400;700'],
        'montserrat' => ['label' => 'Montserrat', 'family' => "'Montserrat', ui-sans-serif, system-ui, sans-serif", 'google' => 'Montserrat:wght@400;500;600;700'],
        'poppins' => ['label' => 'Poppins', 'family' => "'Poppins', ui-sans-serif, system-ui, sans-serif", 'google' => 'Poppins:wght@400;500;600;700'],
        'nunito' => ['label' => 'Nunito', 'family' => "'Nunito', ui-sans-serif, system-ui, sans-serif", 'google' => 'Nunito:wght@400;600;700'],
        'source_sans' => ['label' => 'Source Sans 3', 'family' => "'Source Sans 3', ui-sans-serif, system-ui, sans-serif", 'google' => 'Source+Sans+3:wght@400;500;600;700'],
        'work_sans' => ['label' => 'Work Sans', 'family' => "'Work Sans', ui-sans-serif, system-ui, sans-serif", 'google' => 'Work+Sans:wght@400;500;600;700'],
        'raleway' => ['label' => 'Raleway', 'family' => "'Raleway', ui-sans-serif, system-ui, sans-serif", 'google' => 'Raleway:wght@400;500;600;700'],
        'rubik' => ['label' => 'Rubik', 'family' => "'Rubik', ui-sans-serif, system-ui, sans-serif", 'google' => 'Rubik:wght@400;500;600;700'],
        'mulish' => ['label' => 'Mulish', 'family' => "'Mulish', ui-sans-serif, system-ui, sans-serif", 'google' => 'Mulish:wght@400;600;700'],
        'karla' => ['label' => 'Karla', 'family' => "'Karla', ui-sans-serif, system-ui, sans-serif", 'google' => 'Karla:wght@400;500;700'],
        'dm_sans' => ['label' => 'DM Sans', 'family' => "'DM Sans', ui-sans-serif, system-ui, sans-serif", 'google' => 'DM+Sans:wght@400;500;700'],
        'manrope' => ['label' => 'Manrope', 'family' => "'Manrope', ui-sans-serif, system-ui, sans-serif", 'google' => 'Manrope:wght@400;500;600;700'],
        'outfit' => ['label' => 'Outfit', 'family' => "'Outfit', ui-sans-serif, system-ui, sans-serif", 'google' => 'Outfit:wght@400;500;600;700'],
        'plus_jakarta_sans' => ['label' => 'Plus Jakarta Sans', 'family' => "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif", 'google' => 'Plus+Jakarta+Sans:wght@400;500;600;700'],
        'urbanist' => ['label' => 'Urbanist', 'family' => "'Urbanist', ui-sans-serif, system-ui, sans-serif", 'google' => 'Urbanist:wght@400;500;600;700'],
        'figtree' => ['label' => 'Figtree', 'family' => "'Figtree', ui-sans-serif, system-ui, sans-serif", 'google' => 'Figtree:wght@400;500;600;700'],
        'space_grotesk' => ['label' => 'Space Grotesk', 'family' => "'Space Grotesk', ui-sans-serif, system-ui, sans-serif", 'google' => 'Space+Grotesk:wght@400;500;600;700'],

        // Serif
        'playfair' => ['label' => 'Playfair Display', 'family' => "'Playfair Display', ui-serif, serif", 'google' => 'Playfair+Display:wght@400;600;700'],
        'merriweather' => ['label' => 'Merriweather', 'family' => "'Merriweather', ui-serif, serif", 'google' => 'Merriweather:wght@400;700'],
        'lora' => ['label' => 'Lora', 'family' => "'Lora', ui-serif, serif", 'google' => 'Lora:wght@400;500;600;700'],
        'pt_serif' => ['label' => 'PT Serif', 'family' => "'PT Serif', ui-serif, serif", 'google' => 'PT+Serif:wght@400;700'],
        'source_serif' => ['label' => 'Source Serif 4', 'family' => "'Source Serif 4', ui-serif, serif", 'google' => 'Source+Serif+4:wght@400;600;700'],
        'libre_baskerville' => ['label' => 'Libre Baskerville', 'family' => "'Libre Baskerville', ui-serif, serif", 'google' => 'Libre+Baskerville:wght@400;700'],
        'crimson_text' => ['label' => 'Crimson Text', 'family' => "'Crimson Text', ui-serif, serif", 'google' => 'Crimson+Text:wght@400;600;700'],
        'eb_garamond' => ['label' => 'EB Garamond', 'family' => "'EB Garamond', ui-serif, serif", 'google' => 'EB+Garamond:wght@400;500;600;700'],

        // Display / headings
        'bebas_neue' => ['label' => 'Bebas Neue', 'family' => "'Bebas Neue', ui-sans-serif, system-ui, sans-serif", 'google' => 'Bebas+Neue'],
        'oswald' => ['label' => 'Oswald', 'family' => "'Oswald', ui-sans-serif, system-ui, sans-serif", 'google' => 'Oswald:wght@400;500;600;700'],
        'anton' => ['label' => 'Anton', 'family' => "'Anton', ui-sans-serif, system-ui, sans-serif", 'google' => 'Anton'],
        'abril_fatface' => ['label' => 'Abril Fatface', 'family' => "'Abril Fatface', ui-serif, serif", 'google' => 'Abril+Fatface'],

        // Monospace
        'jetbrains_mono' => ['label' => 'JetBrains Mono', 'family' => "'JetBrains Mono', ui-monospace, monospace", 'google' => 'JetBrains+Mono:wght@400;500;700'],
        'roboto_mono' => ['label' => 'Roboto Mono', 'family' => "'Roboto Mono', ui-monospace, monospace", 'google' => 'Roboto+Mono:wght@400;500;700'],
        'source_code_pro' => ['label' => 'Source Code Pro', 'family' => "'Source Code Pro', ui-monospace, monospace", 'google' => 'Source+Code+Pro:wght@400;500;600;700'],
        'ibm_plex_mono' => ['label' => 'IBM Plex Mono', 'family' => "'IBM Plex Mono', ui-monospace, monospace", 'google' => 'IBM+Plex+Mono:wght@400;500;600;700'],

        // Script / display accent
        'caveat' => ['label' => 'Caveat (corsivo)', 'family' => "'Caveat', cursive", 'google' => 'Caveat:wght@400;600;700'],
    ];

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::$fonts)->mapWithKeys(
            fn (array $font, string $key) => [$key => $font['label']]
        )->toArray();
    }

    public static function cssFamily(?string $key): string
    {
        return self::$fonts[$key]['family'] ?? self::$fonts['default']['family'];
    }

    public static function googleFontsUrl(?string $key): ?string
    {
        $google = self::$fonts[$key]['google'] ?? null;

        return $google ? 'https://fonts.googleapis.com/css2?family='.$google.'&display=swap' : null;
    }
}
