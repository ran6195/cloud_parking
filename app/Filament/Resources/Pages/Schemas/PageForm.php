<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Filament\Blocks\PageBuilderBlocks;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Page')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Contenuto')
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpanFull(),
                                TextInput::make('slug')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->columnSpanFull(),
                                Textarea::make('excerpt')
                                    ->rows(2)
                                    ->helperText('Breve descrizione, usata anche come anteprima nelle pagine hub.')
                                    ->columnSpanFull(),
                                Builder::make('blocks')
                                    ->blocks(PageBuilderBlocks::make())
                                    ->collapsible()
                                    ->blockNumbers(false)
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('SEO')
                            ->schema([
                                TextInput::make('meta_title')
                                    ->label('Meta title')
                                    ->maxLength(60)
                                    ->helperText('Se vuoto viene usato il titolo della pagina. Consigliati max 60 caratteri.')
                                    ->columnSpanFull(),
                                Textarea::make('meta_description')
                                    ->label('Meta description')
                                    ->rows(3)
                                    ->maxLength(320)
                                    ->helperText('Se vuoto viene usato il riassunto. Consigliati 120-160 caratteri.')
                                    ->columnSpanFull(),
                                TextInput::make('canonical_url')
                                    ->label('Canonical URL')
                                    ->url()
                                    ->helperText('Lasciare vuoto per usare l\'URL della pagina stessa.')
                                    ->columnSpanFull(),
                                TextInput::make('og_title')
                                    ->label('Open Graph title')
                                    ->columnSpanFull(),
                                Textarea::make('og_description')
                                    ->label('Open Graph description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Toggle::make('robots_index')
                                    ->label('Indicizzabile (index)')
                                    ->default(true),
                                Toggle::make('robots_follow')
                                    ->label('Segui i link (follow)')
                                    ->default(true),
                            ])
                            ->columns(2),

                        Tab::make('Impostazioni')
                            ->schema([
                                Toggle::make('is_published')
                                    ->label('Pubblicata'),
                                DateTimePicker::make('published_at')
                                    ->label('Pubblica il')
                                    ->helperText('Lasciare vuoto per pubblicare subito.'),
                                Toggle::make('in_hub')
                                    ->label('Mostra nella pagina "Novità"')
                                    ->helperText('Include la pagina nell\'elenco delle pagine SEO in evidenza.'),
                                Toggle::make('is_homepage')
                                    ->label('Usa come homepage')
                                    ->helperText('Questa pagina verrà mostrata su "/". Solo una pagina dovrebbe avere questo flag attivo.'),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
