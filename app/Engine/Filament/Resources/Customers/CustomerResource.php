<?php

namespace App\Engine\Filament\Resources\Customers;

use App\Engine\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Engine\Models\Customer;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * Clienti: nascono con le prenotazioni (uno per email), qui si correggono i contatti.
 */
class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $slug = 'clienti';

    protected static ?string $navigationLabel = 'Clienti';

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clienti';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('first_name')->label('Nome')->required()->maxLength(100),
            TextInput::make('last_name')->label('Cognome')->required()->maxLength(100),
            TextInput::make('email')->label('Email')->email()->required()->maxLength(255)
                ->unique(table: Customer::class, column: 'email', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at')),
            TextInput::make('phone')->label('Telefono')->required()->maxLength(30),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('reservations'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('last_name')->label('Cliente')
                    ->formatStateUsing(fn (Customer $record) => $record->first_name.' '.$record->last_name)
                    ->searchable(['first_name', 'last_name'])->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('phone')->label('Telefono')->searchable(),
                TextColumn::make('reservations_count')->label('Prenotazioni')->sortable()->alignEnd(),
                TextColumn::make('created_at')->label('Dal')->date('d/m/Y')->sortable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCustomers::route('/')];
    }
}
