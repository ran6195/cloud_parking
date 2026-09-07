<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public ?string $site_tagline;

    public ?string $default_meta_description;

    public ?string $default_og_image;

    public ?string $company_name;

    public ?string $vat_number;

    public ?string $legal_address;

    public ?string $operational_address;

    public ?string $phone;

    public ?string $whatsapp;

    public ?string $email;

    public ?string $opening_hours;

    public ?string $facebook_url;

    public ?string $instagram_url;

    public ?string $linkedin_url;

    public ?string $google_analytics_id;

    public ?string $google_tag_manager_id;

    public ?string $search_console_verification;

    public ?string $robots_txt;

    public ?string $header_logo;

    public ?string $header_bg_color;

    public ?string $header_text_color;

    public ?string $header_font;

    public bool $header_show_phone;

    public bool $header_show_hours;

    public ?string $header_cta_label;

    public ?string $header_cta_url;

    public static function group(): string
    {
        return 'general';
    }
}
