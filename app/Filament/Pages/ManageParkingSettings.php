<?php

namespace App\Filament\Pages;

use App\Settings\ParkingSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageParkingSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Parcheggio';

    protected static ?string $title = 'Parcheggio';

    protected static string $settings = ParkingSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Servizio')
                    ->schema([
                        TextInput::make('shuttle_minutes')
                            ->label('Minuti di navetta fino al terminal')
                            ->helperText('Usato nei testi con {minuti}.')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('free_cancellation_hours')
                            ->label('Ore di cancellazione gratuita')
                            ->helperText('Usato nei testi con {ore_cancellazione}.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Prezzi e prenotazioni')
                    ->description('Listini, servizi e prenotazioni si gestiscono nel pannello del motore: /motore. Il sito li legge da lì.')
                    ->schema([]),

                Section::make('Mappa e recensioni')
                    ->schema([
                        TextInput::make('maps_url')
                            ->label('Link "Apri in Google Maps"')
                            ->url()
                            ->columnSpanFull(),
                        TextInput::make('maps_embed_url')
                            ->label('URL di embed della mappa')
                            ->helperText('Da Google Maps: Condividi → Incorpora una mappa → copia solo l\'indirizzo in src="...".')
                            ->url()
                            ->columnSpanFull(),
                        TextInput::make('google_rating')
                            ->label('Voto medio Google')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5)
                            ->step(0.1),
                        TextInput::make('google_reviews_count')
                            ->label('Numero recensioni Google')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->columns(2),
            ]);
    }
}
