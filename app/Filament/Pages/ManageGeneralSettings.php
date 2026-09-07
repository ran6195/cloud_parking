<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use App\Support\HeaderFonts;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageGeneralSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Impostazioni';

    protected static string $settings = GeneralSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Settings')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Azienda')
                            ->schema([
                                TextInput::make('site_name')->label('Nome sito')->required(),
                                TextInput::make('site_tagline')->label('Slogan'),
                                TextInput::make('company_name')->label('Ragione sociale'),
                                TextInput::make('vat_number')->label('P.IVA'),
                                TextInput::make('legal_address')->label('Sede legale'),
                                TextInput::make('operational_address')->label('Sede operativa'),
                                TextInput::make('phone')->label('Telefono')->tel(),
                                TextInput::make('whatsapp')->label('WhatsApp')->tel(),
                                TextInput::make('email')->label('Email')->email(),
                                TextInput::make('opening_hours')->label('Orari'),
                                TextInput::make('facebook_url')->label('Facebook')->url(),
                                TextInput::make('instagram_url')->label('Instagram')->url(),
                                TextInput::make('linkedin_url')->label('LinkedIn')->url(),
                            ])
                            ->columns(2),

                        Tab::make('SEO')
                            ->schema([
                                Textarea::make('default_meta_description')
                                    ->label('Meta description di default')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                TextInput::make('default_og_image')
                                    ->label('Immagine Open Graph di default (URL)')
                                    ->columnSpanFull(),
                                TextInput::make('google_analytics_id')->label('Google Analytics ID'),
                                TextInput::make('google_tag_manager_id')->label('Google Tag Manager ID'),
                                TextInput::make('search_console_verification')->label('Meta verifica Search Console'),
                                Textarea::make('robots_txt')
                                    ->label('robots.txt')
                                    ->rows(5)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Tab::make('Header')
                            ->schema([
                                FileUpload::make('header_logo')
                                    ->label('Logo')
                                    ->image()
                                    ->directory('settings')
                                    ->imageEditor()
                                    ->columnSpanFull(),
                                ColorPicker::make('header_bg_color')
                                    ->label('Colore sfondo'),
                                ColorPicker::make('header_text_color')
                                    ->label('Colore testo'),
                                Select::make('header_font')
                                    ->label('Font')
                                    ->options(HeaderFonts::options())
                                    ->native(false),
                                Toggle::make('header_show_phone')
                                    ->label('Mostra telefono'),
                                Toggle::make('header_show_hours')
                                    ->label('Mostra orari'),
                                TextInput::make('header_cta_label')
                                    ->label('Testo pulsante (opzionale)'),
                                TextInput::make('header_cta_url')
                                    ->label('Link pulsante'),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
