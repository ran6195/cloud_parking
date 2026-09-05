<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', config('app.name'));
        $this->migrator->add('general.site_tagline', null);
        $this->migrator->add('general.default_meta_description', null);
        $this->migrator->add('general.default_og_image', null);
        $this->migrator->add('general.company_name', null);
        $this->migrator->add('general.vat_number', null);
        $this->migrator->add('general.legal_address', null);
        $this->migrator->add('general.operational_address', null);
        $this->migrator->add('general.phone', null);
        $this->migrator->add('general.whatsapp', null);
        $this->migrator->add('general.email', null);
        $this->migrator->add('general.opening_hours', null);
        $this->migrator->add('general.facebook_url', null);
        $this->migrator->add('general.instagram_url', null);
        $this->migrator->add('general.linkedin_url', null);
        $this->migrator->add('general.google_analytics_id', null);
        $this->migrator->add('general.google_tag_manager_id', null);
        $this->migrator->add('general.search_console_verification', null);
        $this->migrator->add('general.robots_txt', "User-agent: *\nAllow: /");
    }
};
