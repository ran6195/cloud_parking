<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Il nuovo design ha header, colori e font fissi: le opzioni di
     * personalizzazione di header e barra superiore non servono piu'.
     * Il menu diventa una lista di link (anche ancore alle sezioni).
     */
    public function up(): void
    {
        foreach ([
            'header_logo', 'header_bg_color', 'header_text_color', 'header_font',
            'header_show_phone', 'header_show_hours',
            'topbar_enabled', 'topbar_bg_color', 'topbar_text_color', 'topbar_height',
            'topbar_show_phone', 'topbar_show_whatsapp', 'topbar_show_hours', 'topbar_show_address',
        ] as $name) {
            $this->migrator->deleteIfExists("general.{$name}");
        }

        $this->migrator->update('general.header_cta_label', fn ($value) => $value ?: 'Prenota il posto');
        $this->migrator->update('general.header_cta_url', fn ($value) => $value ?: '/#prenota');

        $this->migrator->add('general.menu_items', [
            ['label' => 'Come funziona', 'url' => '/#come'],
            ['label' => 'Tariffe', 'url' => '/#tariffe'],
            ['label' => 'Dove siamo', 'url' => '/#dove'],
            ['label' => 'Domande', 'url' => '/#domande'],
        ]);

        $this->migrator->add('general.footer_links', [
            ['label' => 'Privacy', 'url' => '/privacy'],
            ['label' => 'Cookie', 'url' => '/cookie'],
            ['label' => 'Condizioni del servizio', 'url' => '/condizioni-del-servizio'],
        ]);
    }
};
