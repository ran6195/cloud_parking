<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.header_logo', null);
        $this->migrator->add('general.header_bg_color', '#ffffff');
        $this->migrator->add('general.header_text_color', '#0f172a');
        $this->migrator->add('general.header_font', 'default');
        $this->migrator->add('general.header_show_phone', true);
        $this->migrator->add('general.header_show_hours', false);
        $this->migrator->add('general.header_cta_label', null);
        $this->migrator->add('general.header_cta_url', null);
    }
};
