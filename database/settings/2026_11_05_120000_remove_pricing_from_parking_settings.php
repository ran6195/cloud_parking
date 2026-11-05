<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Prezzi e link di prenotazione passano al motore prenotazioni: il CMS li legge dall'API.
 */
return new class extends SettingsMigration
{
    private const REMOVED = [
        'parking.booking_url' => null,
        'parking.price_up_to_3_days' => 25.0,
        'parking.price_week' => 45.0,
        'parking.price_two_weeks' => 75.0,
        'parking.price_extra_day' => 5.0,
        'parking.price_covered_per_day' => 3.0,
    ];

    public function up(): void
    {
        foreach (array_keys(self::REMOVED) as $property) {
            $this->migrator->deleteIfExists($property);
        }
    }

    public function down(): void
    {
        foreach (self::REMOVED as $property => $default) {
            $this->migrator->add($property, $default);
        }
    }
};
