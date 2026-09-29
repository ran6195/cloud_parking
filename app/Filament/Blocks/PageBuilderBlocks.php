<?php

namespace App\Filament\Blocks;

use App\Support\Icons;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;

class PageBuilderBlocks
{
    public const PLACEHOLDERS_HELP = 'Puoi usare {minuti}, {telefono}, {orari}, {ore_cancellazione}, {indirizzo}, {nome}.';

    /**
     * @return array<Block>
     */
    public static function make(): array
    {
        return [
            Block::make('hero')
                ->label('Hero (apertura pagina)')
                ->icon('heroicon-o-sparkles')
                ->schema([
                    TextInput::make('heading')->label('Titolo (H1)')->required(),
                    Textarea::make('subheading')->label('Testo')->rows(3)->helperText(self::PLACEHOLDERS_HELP),
                    Fieldset::make('Cartello segnaletico')
                        ->schema([
                            Toggle::make('show_sign')->label('Mostra il cartello')->default(true)->columnSpanFull(),
                            TextInput::make('sign_title')->label('Titolo cartello')->default('Aeroporto di Bologna'),
                            TextInput::make('sign_text')
                                ->label('Riga sotto')
                                ->default('{minuti} minuti in navetta, inclusa nel prezzo')
                                ->helperText(self::PLACEHOLDERS_HELP),
                        ]),
                    Toggle::make('show_booking_form')
                        ->label('Mostra il modulo di calcolo prezzo')
                        ->helperText('Se disattivato viene mostrata l\'immagine, se presente.')
                        ->default(true),
                    FileUpload::make('image')
                        ->label('Immagine')
                        ->image()
                        ->directory('pages/blocks')
                        ->imageEditor(),
                    TextInput::make('cta_label')->label('Testo pulsante'),
                    TextInput::make('cta_url')->label('Link pulsante'),
                ]),

            Block::make('features')
                ->label('Punti di forza (striscia con icone)')
                ->icon('heroicon-o-squares-plus')
                ->schema([
                    TextInput::make('heading')->label('Titolo (opzionale)'),
                    Repeater::make('items')
                        ->label('Elementi')
                        ->schema([
                            Select::make('icon')->label('Icona')->options(Icons::options())->native(false),
                            TextInput::make('title')->label('Titolo')->required(),
                            Textarea::make('description')->label('Testo')->rows(2)->helperText(self::PLACEHOLDERS_HELP),
                        ])
                        ->columns(3)
                        ->reorderable(),
                    ...self::sectionFields('terra'),
                ]),

            Block::make('steps')
                ->label('Come funziona (passaggi numerati)')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    TextInput::make('heading')->label('Titolo')->required(),
                    Textarea::make('subheading')->label('Sottotitolo')->rows(2),
                    Repeater::make('items')
                        ->label('Passaggi')
                        ->schema([
                            TextInput::make('title')->label('Titolo')->required(),
                            Textarea::make('description')->label('Testo')->rows(2)->helperText(self::PLACEHOLDERS_HELP),
                        ])
                        ->columns(2)
                        ->reorderable(),
                    ...self::sectionFields('terra', 'come'),
                ]),

            Block::make('pricing')
                ->label('Tariffe')
                ->icon('heroicon-o-currency-euro')
                ->schema([
                    TextInput::make('heading')->label('Titolo')->required(),
                    Textarea::make('text')->label('Testo')->rows(3)->helperText(self::PLACEHOLDERS_HELP),
                    TextInput::make('table_caption')->label('Didascalia tabella')->default('Posto scoperto, navetta inclusa'),
                    TextInput::make('button_label')->label('Testo pulsante')->default('Calcola il prezzo per le tue date'),
                    TextInput::make('button_url')->label('Link pulsante')->default('/#prenota'),
                    ...self::sectionFields('carta', 'tariffe'),
                ])
                ->columns(2),

            Block::make('testimonials')
                ->label('Recensioni')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    TextInput::make('heading')->label('Titolo'),
                    Toggle::make('show_rating')->label('Mostra voto e numero recensioni Google')->default(true),
                    Repeater::make('items')
                        ->label('Recensioni')
                        ->schema([
                            Textarea::make('quote')->label('Testo')->required()->rows(3)->columnSpanFull(),
                            TextInput::make('author')->label('Autore')->required(),
                            TextInput::make('role')->label('Dettaglio (es. data o destinazione)'),
                        ])
                        ->columns(2)
                        ->reorderable(),
                    ...self::sectionFields('terra'),
                ]),

            Block::make('location')
                ->label('Dove siamo (con mappa)')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    TextInput::make('heading')->label('Titolo')->required(),
                    Textarea::make('text')
                        ->label('Testo')
                        ->rows(2)
                        ->helperText('L\'indirizzo viene preso dalle Impostazioni. '.self::PLACEHOLDERS_HELP),
                    Repeater::make('directions')
                        ->label('Indicazioni')
                        ->schema([
                            TextInput::make('term')->label('Voce (es. Dall\'autostrada)')->required(),
                            Textarea::make('description')->label('Descrizione')->rows(2)->required(),
                        ])
                        ->columns(2)
                        ->reorderable(),
                    Toggle::make('show_hours')->label('Aggiungi gli orari di apertura dalle Impostazioni')->default(true),
                    TextInput::make('button_label')->label('Testo pulsante mappa')->default('Apri in Google Maps'),
                    ...self::sectionFields('terra', 'dove'),
                ]),

            Block::make('faq')
                ->label('Domande frequenti')
                ->icon('heroicon-o-question-mark-circle')
                ->schema([
                    TextInput::make('heading')->label('Titolo'),
                    Textarea::make('intro')->label('Testo a lato')->rows(2)->helperText(self::PLACEHOLDERS_HELP),
                    Repeater::make('items')
                        ->label('Domande')
                        ->schema([
                            TextInput::make('question')->label('Domanda')->required(),
                            Textarea::make('answer')->label('Risposta')->required()->rows(3)->helperText(self::PLACEHOLDERS_HELP),
                        ])
                        ->reorderable(),
                    ...self::sectionFields('carta', 'domande'),
                ]),

            Block::make('cta')
                ->label('Call to action (banda gialla)')
                ->icon('heroicon-o-megaphone')
                ->schema([
                    TextInput::make('heading')->label('Titolo')->required(),
                    Textarea::make('text')->label('Testo')->rows(2),
                    TextInput::make('button_label')->label('Testo pulsante')->required(),
                    TextInput::make('button_url')->label('Link pulsante')->required(),
                    TextInput::make('anchor')->label('Ancora (id)')->alphaDash(),
                ]),

            Block::make('text_image')
                ->label('Testo + Immagine')
                ->icon('heroicon-o-photo')
                ->schema([
                    TextInput::make('heading')->label('Titolo'),
                    RichEditor::make('content')->label('Testo'),
                    FileUpload::make('image')
                        ->label('Immagine')
                        ->image()
                        ->directory('pages/blocks')
                        ->imageEditor(),
                    Select::make('image_position')
                        ->label('Posizione immagine')
                        ->options([
                            'left' => 'Immagine a sinistra',
                            'right' => 'Immagine a destra',
                        ])
                        ->default('right'),
                    ...self::sectionFields('carta'),
                ]),

            Block::make('gallery')
                ->label('Galleria')
                ->icon('heroicon-o-squares-2x2')
                ->schema([
                    TextInput::make('heading')->label('Titolo'),
                    FileUpload::make('images')
                        ->label('Immagini')
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->directory('pages/blocks'),
                    ...self::sectionFields('terra'),
                ]),

            Block::make('link_list')
                ->label('Lista link')
                ->icon('heroicon-o-link')
                ->schema([
                    TextInput::make('heading')->label('Titolo'),
                    Repeater::make('items')
                        ->label('Link')
                        ->schema([
                            TextInput::make('label')->label('Testo')->required(),
                            TextInput::make('url')->label('Link')->required(),
                        ])
                        ->columns(2)
                        ->reorderable(),
                    ...self::sectionFields('terra'),
                ]),

            Block::make('hub_list')
                ->label('Elenco pagine in evidenza (Novità)')
                ->icon('heroicon-o-newspaper')
                ->schema([
                    TextInput::make('heading')->label('Titolo'),
                    Textarea::make('intro')->label('Introduzione')->rows(2),
                    ...self::sectionFields('terra'),
                ]),

            Block::make('contact_form')
                ->label('Form di contatto')
                ->icon('heroicon-o-envelope')
                ->schema([
                    TextInput::make('heading')->label('Titolo'),
                    Textarea::make('subheading')->label('Testo')->rows(2)->helperText(self::PLACEHOLDERS_HELP),
                    TextInput::make('submit_label')->label('Testo pulsante')->default('Invia richiesta'),
                    ...self::sectionFields('terra'),
                ]),
        ];
    }

    /**
     * Campi comuni alle sezioni: sfondo e ancora per i link del menu.
     *
     * @return array<Component|Field>
     */
    protected static function sectionFields(string $defaultBackground, ?string $defaultAnchor = null): array
    {
        return [
            Fieldset::make('Sezione')
                ->schema([
                    Select::make('background')
                        ->label('Sfondo')
                        ->options([
                            'terra' => 'Grigio chiaro',
                            'carta' => 'Bianco',
                        ])
                        ->default($defaultBackground)
                        ->selectablePlaceholder(false),
                    TextInput::make('anchor')
                        ->label('Ancora (id)')
                        ->helperText('Per linkare la sezione dal menu, es. /#tariffe.')
                        ->alphaDash()
                        ->default($defaultAnchor),
                ])
                ->columnSpanFull(),
        ];
    }
}
