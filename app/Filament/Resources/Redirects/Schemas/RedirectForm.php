<?php

namespace App\Filament\Resources\Redirects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('from')
                    ->label('URL di origine')
                    ->helperText('Path relativo, es. /vecchia-pagina')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('to')
                    ->label('URL di destinazione')
                    ->helperText('Path relativo o URL assoluto')
                    ->required(),
                Select::make('status_code')
                    ->label('Tipo redirect')
                    ->options([
                        301 => '301 - Permanente',
                        302 => '302 - Temporaneo',
                    ])
                    ->default(301)
                    ->required(),
            ]);
    }
}
