<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.topbar_enabled', true);
        $this->migrator->add('general.topbar_bg_color', '#0f172a');
        $this->migrator->add('general.topbar_text_color', '#f8fafc');
        $this->migrator->add('general.topbar_show_phone', true);
        $this->migrator->add('general.topbar_show_whatsapp', true);
        $this->migrator->add('general.topbar_show_hours', true);
        $this->migrator->add('general.topbar_show_address', true);
    }
};
