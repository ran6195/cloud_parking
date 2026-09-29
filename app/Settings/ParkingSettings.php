<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ParkingSettings extends Settings
{
    public int $shuttle_minutes;

    public int $free_cancellation_hours;

    public ?string $booking_url;

    public ?string $maps_url;

    public ?string $maps_embed_url;

    public ?float $google_rating;

    public ?int $google_reviews_count;

    public float $price_up_to_3_days;

    public float $price_week;

    public float $price_two_weeks;

    public float $price_extra_day;

    public ?float $price_covered_per_day;

    public static function group(): string
    {
        return 'parking';
    }
}
