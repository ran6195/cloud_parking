<?php

namespace App\Filament\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class PageBuilderBlocks
{
    /**
     * @return array<Block>
     */
    public static function make(): array
    {
        return [
            Block::make('hero')
                ->label('Hero')
                ->icon('heroicon-o-photo')
                ->schema([
                    TextInput::make('heading')->required(),
                    Textarea::make('subheading')->rows(2),
                    FileUpload::make('image')
                        ->image()
                        ->directory('pages/blocks')
                        ->imageEditor(),
                    TextInput::make('cta_label'),
                    TextInput::make('cta_url')->url(),
                ]),

            Block::make('text_image')
                ->label('Testo + Immagine')
                ->icon('heroicon-o-photo')
                ->schema([
                    TextInput::make('heading'),
                    RichEditor::make('content'),
                    FileUpload::make('image')
                        ->image()
                        ->directory('pages/blocks')
                        ->imageEditor(),
                    Select::make('image_position')
                        ->options([
                            'left' => 'Immagine a sinistra',
                            'right' => 'Immagine a destra',
                        ])
                        ->default('right'),
                ]),

            Block::make('gallery')
                ->label('Galleria')
                ->icon('heroicon-o-squares-2x2')
                ->schema([
                    FileUpload::make('images')
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->directory('pages/blocks'),
                ]),

            Block::make('cta')
                ->label('Call to action')
                ->icon('heroicon-o-megaphone')
                ->schema([
                    TextInput::make('heading')->required(),
                    Textarea::make('text')->rows(2),
                    TextInput::make('button_label')->required(),
                    TextInput::make('button_url')->required()->url(),
                ]),

            Block::make('features')
                ->label('Servizi / Features')
                ->icon('heroicon-o-squares-plus')
                ->schema([
                    TextInput::make('heading'),
                    Repeater::make('items')
                        ->schema([
                            TextInput::make('icon')->helperText('Nome icona Heroicon, es. heroicon-o-truck'),
                            TextInput::make('title')->required(),
                            Textarea::make('description')->rows(2),
                        ])
                        ->columns(3)
                        ->reorderable(),
                ]),

            Block::make('testimonials')
                ->label('Testimonianze')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    Repeater::make('items')
                        ->schema([
                            TextInput::make('author')->required(),
                            TextInput::make('role'),
                            Textarea::make('quote')->required()->rows(3),
                            FileUpload::make('avatar')
                                ->image()
                                ->directory('pages/blocks'),
                        ])
                        ->columns(2)
                        ->reorderable(),
                ]),

            Block::make('faq')
                ->label('FAQ')
                ->icon('heroicon-o-question-mark-circle')
                ->schema([
                    TextInput::make('heading'),
                    Repeater::make('items')
                        ->schema([
                            TextInput::make('question')->required(),
                            Textarea::make('answer')->required()->rows(3),
                        ])
                        ->reorderable(),
                ]),

            Block::make('link_list')
                ->label('Lista link')
                ->icon('heroicon-o-link')
                ->schema([
                    TextInput::make('heading'),
                    Repeater::make('items')
                        ->schema([
                            TextInput::make('label')->required(),
                            TextInput::make('url')->required(),
                        ])
                        ->columns(2)
                        ->reorderable(),
                ]),

            Block::make('hub_list')
                ->label('Elenco pagine in evidenza (Novità)')
                ->icon('heroicon-o-newspaper')
                ->schema([
                    TextInput::make('heading'),
                    Textarea::make('intro')->rows(2),
                ]),

            Block::make('contact_form')
                ->label('Form di contatto')
                ->icon('heroicon-o-envelope')
                ->schema([
                    TextInput::make('heading'),
                    Textarea::make('subheading')->rows(2),
                    TextInput::make('submit_label')->default('Invia richiesta'),
                ]),
        ];
    }
}
