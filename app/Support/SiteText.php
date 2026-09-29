<?php

namespace App\Support;

use App\Settings\GeneralSettings;
use App\Settings\ParkingSettings;

class SiteText
{
    /**
     * Segnaposto utilizzabili nei testi dei blocchi, sostituiti con i dati delle impostazioni.
     *
     * @return array<string, string>
     */
    public static function placeholders(): array
    {
        $general = app(GeneralSettings::class);
        $parking = app(ParkingSettings::class);

        return [
            '{minuti}' => (string) $parking->shuttle_minutes,
            '{ore_cancellazione}' => (string) $parking->free_cancellation_hours,
            '{telefono}' => (string) $general->phone,
            '{orari}' => (string) $general->opening_hours,
            '{indirizzo}' => (string) $general->operational_address,
            '{nome}' => (string) $general->site_name,
        ];
    }

    public static function fill(?string $text): string
    {
        if (blank($text)) {
            return '';
        }

        return strtr($text, self::placeholders());
    }

    public static function euro(float $amount): string
    {
        $decimals = floor($amount) == $amount ? 0 : 2;

        return '€ '.number_format($amount, $decimals, ',', '.');
    }

    public static function telHref(?string $phone): string
    {
        return 'tel:'.preg_replace('/[^\d+]/', '', (string) $phone);
    }
}
