<?php

namespace App\Support;

class Icons
{
    /**
     * Icone selezionabili dal pannello per i blocchi (file in resources/views/components/icona/paths).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'bus' => 'Navetta',
            'shield-check' => 'Sicurezza / sorveglianza',
            'moon' => 'Notte / orari estesi',
            'receipt' => 'Prezzo / ricevuta',
            'plane' => 'Aereo',
            'car' => 'Auto',
            'key' => 'Chiavi',
            'luggage' => 'Bagagli',
            'clock' => 'Orario',
            'camera' => 'Videosorveglianza',
            'map-pin' => 'Posizione',
            'phone' => 'Telefono',
            'star' => 'Stella',
            'check' => 'Spunta',
        ];
    }
}
