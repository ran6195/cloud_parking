<?php

namespace App\Engine\Filament\Resources\BlockedDates;

use App\Engine\Filament\Resources\BlockedDates\Pages\ManageBlockedDates;
use App\Engine\Models\BlockedDate;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Giorni in cui non si accettano soste. Bloccano anche il giorno di ritiro (vedi docs/availability.md).
 */
class BlockedDateResource extends Resource
{
    protected static ?string $model = BlockedDate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $slug = 'date-bloccate';

    protected static ?string $navigationLabel = 'Date bloccate';

    protected static ?string $modelLabel = 'data bloccata';

    protected static ?string $pluralModelLabel = 'date bloccate';

    protected static ?int $navigationSort = 5;

    private const WEEKDAYS = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')->label('Giorno')->required()->unique(table: BlockedDate::class, column: 'date', ignoreRecord: true),
            TextInput::make('reason')->label('Motivo')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')->label('Giorno')->date('d/m/Y')->sortable(),
                TextColumn::make('weekday')->label('')->state(fn (BlockedDate $record) => self::WEEKDAYS[(int) $record->date->format('w')]),
                TextColumn::make('reason')->label('Motivo')->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('upcoming')->label('Solo da oggi in poi')->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->where('date', '>=', CarbonImmutable::today('Europe/Rome')->format('Y-m-d')),
                        false: fn (Builder $query) => $query->where('date', '<', CarbonImmutable::today('Europe/Rome')->format('Y-m-d')),
                    ),
            ])
            ->recordActions([DeleteAction::make()->label('Sblocca')])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->label('Sblocca')])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBlockedDates::route('/')];
    }
}
