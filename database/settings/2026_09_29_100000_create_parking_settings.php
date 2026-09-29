<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Valori segnaposto: vanno sostituiti con i dati reali del gestore
     * dal pannello (Impostazioni parcheggio).
     */
    public function up(): void
    {
        $this->migrator->add('parking.shuttle_minutes', 5);
        $this->migrator->add('parking.free_cancellation_hours', 24);
        $this->migrator->add('parking.booking_url', null);
        $this->migrator->add('parking.maps_url', null);
        $this->migrator->add('parking.maps_embed_url', null);
        $this->migrator->add('parking.google_rating', null);
        $this->migrator->add('parking.google_reviews_count', null);
        $this->migrator->add('parking.price_up_to_3_days', 25.0);
        $this->migrator->add('parking.price_week', 45.0);
        $this->migrator->add('parking.price_two_weeks', 75.0);
        $this->migrator->add('parking.price_extra_day', 5.0);
        $this->migrator->add('parking.price_covered_per_day', 3.0);
    }
};
