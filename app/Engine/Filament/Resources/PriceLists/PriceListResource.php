<?php

namespace App\Engine\Filament\Resources\PriceLists;

use App\Engine\Domain\Pricing\PriceTable;
use App\Engine\Filament\Resources\PriceLists\Pages\CreatePriceList;
use App\Engine\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Engine\Filament\Resources\PriceLists\Pages\ListPriceLists;
use App\Engine\Filament\Support\Euro;
use App\Engine\Models\PriceList;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Listini: periodo, prezzo totale per 1..30 giorni (tutti obbligatori) e giorno aggiuntivo.
 * I periodi non si sovrappongono: lo controllano il form e un vincolo del database.
 */
class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyEuro;

    protected static ?string $slug = 'listini';

    protected static ?string $navigationLabel = 'Listini';

    protected static ?string $modelLabel = 'listino';

    protected static ?string $pluralModelLabel = 'listini';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $prices = [];
        foreach (range(1, PriceTable::DAYS) as $days) {
            $prices[] = TextInput::make("prices.{$days}")
                ->label($days === 1 ? '1 giorno' : "{$days} giorni")
                ->numeric()->minValue(0)->step(0.01)->prefix('€')->required();
        }

        return $schema->components([
            Section::make('Periodo')->columns(4)->schema([
                TextInput::make('name')->label('Nome')->required()->maxLength(100)->columnSpan(2),
                DatePicker::make('starts_on')->label('Valido dal')->required(),
                DatePicker::make('ends_on')->label('al (incluso)')->required()->afterOrEqual('starts_on')
                    ->rule(fn (Get $get, ?PriceList $record) => self::noOverlapRule($get('starts_on'), $record?->id)),
            ]),
            Section::make('Prezzo totale della sosta')
                ->description('Prezzo complessivo per una sosta di N giorni (non al giorno), scelto in base alla data di consegna.')
                ->schema([Grid::make(6)->schema($prices)]),
            Section::make('Oltre il 30° giorno')->schema([
                TextInput::make('extra_day')->label('Prezzo di ogni giorno in più')->numeric()->minValue(0)->step(0.01)->prefix('€')->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('prices'))
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('starts_on')->label('Dal')->date('d/m/Y')->sortable(),
                TextColumn::make('ends_on')->label('Al')->date('d/m/Y')->sortable(),
                TextColumn::make('price_1')->label('1 giorno')->state(fn (PriceList $record) => Euro::format($record->centsByDays()[1] ?? null))->alignEnd(),
                TextColumn::make('price_7')->label('7 giorni')->state(fn (PriceList $record) => Euro::format($record->centsByDays()[7] ?? null))->alignEnd(),
                TextColumn::make('price_30')->label('30 giorni')->state(fn (PriceList $record) => Euro::format($record->centsByDays()[30] ?? null))->alignEnd(),
                TextColumn::make('extra_day_cents')->label('Giorno in più')->formatStateUsing(fn (int $state) => Euro::format($state))->alignEnd(),
                TextColumn::make('source')->label('Origine')->badge()
                    ->formatStateUsing(fn (string $state) => $state === PriceList::SOURCE_LEGACY ? 'Legacy' : 'Manuale')
                    ->color(fn (string $state) => $state === PriceList::SOURCE_LEGACY ? 'gray' : 'primary'),
            ])
            ->recordActions([EditAction::make(), self::duplicateAction(), DeleteAction::make()]);
    }

    /**
     * Copia i prezzi su un nuovo periodo, con un eventuale aumento in percentuale.
     */
    public static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Duplica')
            ->icon('heroicon-o-document-duplicate')
            ->schema([
                TextInput::make('name')->label('Nome')->required()->maxLength(100),
                Grid::make(2)->schema([
                    DatePicker::make('starts_on')->label('Valido dal')->required(),
                    DatePicker::make('ends_on')->label('al (incluso)')->required()->afterOrEqual('starts_on')
                        ->rule(fn (Get $get) => self::noOverlapRule($get('starts_on'), null)),
                ]),
                TextInput::make('increase')->label('Variazione dei prezzi')->numeric()->default(0)->suffix('%')
                    ->minValue(-50)->maxValue(100)->helperText('Ad es. 5 per aumentare del 5%. Gli importi si arrotondano al centesimo.'),
            ])
            ->action(function (PriceList $record, array $data): void {
                $factor = 1 + ((float) ($data['increase'] ?? 0)) / 100;
                $copy = PriceList::query()->create([
                    'name' => $data['name'],
                    'starts_on' => $data['starts_on'],
                    'ends_on' => $data['ends_on'],
                    'extra_day_cents' => (int) round($record->extra_day_cents * $factor),
                    'source' => PriceList::SOURCE_MANUAL,
                ]);
                $copy->syncPrices(array_map(fn (int $cents) => (int) round($cents * $factor), $record->centsByDays()));

                Notification::make()->success()->title("Listino \"{$copy->name}\" creato.")->send();
            });
    }

    public static function noOverlapRule(mixed $startsOn, ?int $ignoreId): Closure
    {
        return function (string $attribute, mixed $endsOn, Closure $fail) use ($startsOn, $ignoreId): void {
            if (! is_string($startsOn) || ! is_string($endsOn) || $startsOn === '' || $endsOn === '') {
                return;
            }

            $overlap = PriceList::query()
                ->where('starts_on', '<=', $endsOn)
                ->where('ends_on', '>=', $startsOn)
                ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId))
                ->first();

            if ($overlap !== null) {
                $fail("Il periodo si sovrappone al listino \"{$overlap->name}\" ({$overlap->starts_on->format('d/m/Y')} – {$overlap->ends_on->format('d/m/Y')}).");
            }
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{attributes: array<string, mixed>, cents: array<int, int>}
     */
    public static function splitFormData(array $data): array
    {
        /** @var array<int|string, mixed> $prices */
        $prices = $data['prices'] ?? [];
        $cents = [];
        foreach (range(1, PriceTable::DAYS) as $days) {
            $cents[$days] = Euro::toCents($prices[$days] ?? $prices[(string) $days] ?? 0);
        }

        return [
            'attributes' => [
                'name' => $data['name'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'extra_day_cents' => Euro::toCents($data['extra_day'] ?? 0),
            ],
            'cents' => $cents,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceLists::route('/'),
            'create' => CreatePriceList::route('/create'),
            'edit' => EditPriceList::route('/{record}/edit'),
        ];
    }
}
