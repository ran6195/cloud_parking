<?php

namespace App\Filament\Resources\Leads\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nome')->disabled(),
                TextInput::make('email')->label('Email')->disabled(),
                TextInput::make('phone')->label('Telefono')->disabled(),
                TextInput::make('source_url')->label('Pagina di provenienza')->disabled(),
                Textarea::make('message')->label('Messaggio')->rows(4)->disabled()->columnSpanFull(),
                Toggle::make('handled')->label('Gestita'),
            ]);
    }
}
