<?php

namespace App\Engine\Filament\Resources\ApiClients;

use App\Engine\Filament\Resources\ApiClients\Pages\ManageApiClients;
use App\Engine\Models\ApiClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Interfacce che usano l'API (form del CMS, PWA, partner). Le chiavi nascono con "Nuovo client".
 */
class ApiClientResource extends Resource
{
    protected static ?string $model = ApiClient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $slug = 'client-api';

    protected static ?string $navigationLabel = 'Client API';

    protected static ?string $modelLabel = 'client API';

    protected static ?string $pluralModelLabel = 'client API';

    protected static ?int $navigationSort = 8;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::fields(withChannel: false));
    }

    /**
     * @return array<int, TextInput|TagsInput>
     */
    public static function fields(bool $withChannel): array
    {
        return array_values(array_filter([
            TextInput::make('name')->label('Nome')->required()->maxLength(100),
            $withChannel ? TextInput::make('channel')->label('Canale')->required()->regex('/^[a-z0-9_]{1,50}$/')
                ->helperText('Registrato sulle prenotazioni: lettere minuscole, cifre e "_" (es. web, pwa).') : null,
            TagsInput::make('allowed_origins')->label('Origini consentite per la chiave pk_')
                ->placeholder('https://sostablq.it')
                ->nestedRecursiveRules(['regex:#^https?://[^/\s]+/?$#i'])
                ->helperText('Indirizzi dei siti che usano la chiave pubblicabile nel browser.'),
            TextInput::make('rate_limit_per_minute')->label('Richieste al minuto')->integer()->minValue(1)
                ->helperText('Vuoto = limite predefinito ('.config('engine.rate_limits.default').').'),
        ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('channel')->label('Canale')->badge(),
                TextColumn::make('publishable_key')->label('Chiave pubblicabile')->copyable()->limit(20),
                TextColumn::make('allowed_origins')->label('Origini')->badge()->placeholder('—'),
                TextColumn::make('last_used_at')->label('Ultimo uso')->since()->placeholder('Mai'),
                IconColumn::make('revoked_at')->label('Attivo')->boolean()->state(fn (ApiClient $record) => ! $record->isRevoked()),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(function (array $data): array {
                    /** @var list<string> $origins */
                    $origins = $data['allowed_origins'] ?? [];
                    $data['allowed_origins'] = array_map(ApiClient::normalizeOrigin(...), $origins);

                    return $data;
                }),
                Action::make('revoke')->label('Revoca')->icon('heroicon-o-no-symbol')->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Le chiavi smettono subito di funzionare. Non si può annullare.')
                    ->visible(fn (ApiClient $record) => ! $record->isRevoked())
                    ->action(fn (ApiClient $record) => $record->update(['revoked_at' => now()])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageApiClients::route('/')];
    }
}
