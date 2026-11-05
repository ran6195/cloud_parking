<?php

namespace App\Engine\Filament\Resources\Discounts;

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Pricing\Discount\DiscountCode;
use App\Engine\Domain\Pricing\Discount\DiscountType;
use App\Engine\Filament\Resources\Discounts\Pages\ManageDiscounts;
use App\Engine\Filament\Support\Euro;
use App\Engine\Models\Discount;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $slug = 'codici-sconto';

    protected static ?string $navigationLabel = 'Codici sconto';

    protected static ?string $modelLabel = 'codice sconto';

    protected static ?string $pluralModelLabel = 'codici sconto';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label('Codice')->required()->maxLength(50)
                ->helperText('Il cliente può scriverlo in minuscolo: si confronta in maiuscolo.')
                ->rule(fn (?Discount $record) => self::uniqueCodeRule($record?->id)),
            Select::make('type')->label('Tipo')->required()->live()
                ->options([DiscountType::Percentage->value => 'Percentuale', DiscountType::FixedAmount->value => 'Importo fisso'])
                ->default(DiscountType::Percentage->value),
            TextInput::make('percentage')->label('Percentuale')->integer()->minValue(1)->maxValue(100)->suffix('%')
                ->visible(fn (Get $get) => self::type($get) === DiscountType::Percentage)->required(fn (Get $get) => self::type($get) === DiscountType::Percentage),
            TextInput::make('amount')->label('Importo')->numeric()->minValue(0.01)->step(0.01)->prefix('€')
                ->visible(fn (Get $get) => self::type($get) === DiscountType::FixedAmount)->required(fn (Get $get) => self::type($get) === DiscountType::FixedAmount),
            TextInput::make('max_uses')->label('Utilizzi massimi')->integer()->minValue(1)
                ->helperText('Vuoto = illimitati. Contano le prenotazioni confermate, pagate o concluse.'),
            DatePicker::make('valid_until')->label('Valido fino al (incluso)')->helperText('Vuoto = senza scadenza.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'reservations as times_used' => fn (Builder $q) => $q->whereIn('status', ReservationStatus::valid()),
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Codice')->searchable()->copyable()->weight('bold'),
                TextColumn::make('type')->label('Sconto')->state(fn (Discount $record) => $record->type === DiscountType::Percentage
                    ? $record->percentage.'%'
                    : Euro::format($record->amount_cents)),
                TextColumn::make('times_used')->label('Utilizzi')
                    ->formatStateUsing(fn (Discount $record, int $state) => $record->max_uses === null ? (string) $state : "{$state} / {$record->max_uses}"),
                TextColumn::make('valid_until')->label('Scadenza')->date('d/m/Y')->placeholder('Nessuna')->sortable(),
            ])
            ->recordActions([EditAction::make()->mutateRecordDataUsing(self::fill(...))->mutateDataUsing(self::dehydrate(...)), DeleteAction::make()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fill(array $data): array
    {
        $data['amount'] = Euro::fromCents(isset($data['amount_cents']) ? (int) $data['amount_cents'] : null);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function dehydrate(array $data): array
    {
        $percentage = ($data['type'] ?? null) === DiscountType::Percentage->value;

        return [
            'code' => $data['code'],
            'type' => $data['type'],
            'percentage' => $percentage ? (int) $data['percentage'] : null,
            'amount_cents' => $percentage ? null : Euro::toCents($data['amount'] ?? 0),
            'max_uses' => filled($data['max_uses'] ?? null) ? (int) $data['max_uses'] : null,
            'valid_until' => $data['valid_until'] ?? null,
        ];
    }

    private static function type(Get $get): ?DiscountType
    {
        $type = $get('type');

        return $type instanceof DiscountType ? $type : DiscountType::tryFrom((string) $type);
    }

    private static function uniqueCodeRule(?int $ignoreId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoreId): void {
            try {
                $code = new DiscountCode((string) $value);
            } catch (InvalidArgumentException) {
                $fail('Codice non valido.');

                return;
            }

            if (Discount::query()->where('code', $code->value)->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId))->exists()) {
                $fail("Il codice {$code->value} esiste già.");
            }
        };
    }

    public static function getPages(): array
    {
        return ['index' => ManageDiscounts::route('/')];
    }
}
