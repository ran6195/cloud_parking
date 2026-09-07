<?php

namespace App\Support;

class HeaderFonts
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
        'inter' => [
            'label' => 'Inter',
            'family' => "'Inter', ui-sans-serif, system-ui, sans-serif",
            'google' => 'Inter:wght@400;500;600;700',
        ],
        'poppins' => [
            'label' => 'Poppins',
            'family' => "'Poppins', ui-sans-serif, system-ui, sans-serif",
            'google' => 'Poppins:wght@400;500;600;700',
        ],
        'roboto' => [
            'label' => 'Roboto',
            'family' => "'Roboto', ui-sans-serif, system-ui, sans-serif",
            'google' => 'Roboto:wght@400;500;700',
        ],
        'montserrat' => [
            'label' => 'Montserrat',
            'family' => "'Montserrat', ui-sans-serif, system-ui, sans-serif",
            'google' => 'Montserrat:wght@400;500;600;700',
        ],
        'playfair' => [
            'label' => 'Playfair Display',
            'family' => "'Playfair Display', ui-serif, serif",
            'google' => 'Playfair+Display:wght@400;600;700',
        ],
        'nunito' => [
            'label' => 'Nunito',
            'family' => "'Nunito', ui-sans-serif, system-ui, sans-serif",
            'google' => 'Nunito:wght@400;600;700',
        ],
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
