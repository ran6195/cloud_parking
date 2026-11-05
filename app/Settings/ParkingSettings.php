<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Dati del parcheggio per i testi del sito. I prezzi e le prenotazioni
 * li gestisce il motore (/api/v1, pannello /motore).
 */
class ParkingSettings extends Settings
{
    public int $shuttle_minutes;

    public int $free_cancellation_hours;

    public ?string $maps_url;

    public ?string $maps_embed_url;

    public ?float $google_rating;

    public ?int $google_reviews_count;

    public static function group(): string
    {
        return 'parking';
    }
}
