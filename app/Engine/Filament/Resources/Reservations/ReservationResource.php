<?php

namespace App\Engine\Filament\Resources\Reservations;

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Filament\Resources\Reservations\Pages\EditReservation;
use App\Engine\Filament\Resources\Reservations\Pages\ListReservations;
use App\Engine\Filament\Resources\Reservations\Pages\ViewReservation;
use App\Engine\Filament\Support\Euro;
use App\Engine\Filament\Support\Labels;
use App\Engine\Http\V1\Presenters\ReservationPresenter;
use App\Engine\Models\Reservation;
use App\Engine\Models\ReservationLine;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $slug = 'prenotazioni';

    protected static ?string $navigationLabel = 'Prenotazioni';

    protected static ?string $modelLabel = 'prenotazione';

    protected static ?string $pluralModelLabel = 'prenotazioni';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Dal pannello si modificano solo i dati che non cambiano il prezzo.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Veicolo e viaggio')->columns(3)->schema([
                TextInput::make('plate')->label('Targa')->required()->maxLength(15),
                TextInput::make('vehicle_make')->label('Marca')->maxLength(50),
                TextInput::make('vehicle_model')->label('Modello')->maxLength(50),
                TextInput::make('outbound_flight')->label('Volo di andata')->maxLength(20),
                TextInput::make('return_flight')->label('Volo di ritorno')->maxLength(20),
                TextInput::make('passengers')->label('Passeggeri')->integer()->minValue(1)->maxValue(9)->required(),
            ]),
            Textarea::make('notes')->label('Note')->rows(3)->maxLength(1000)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Prenotazione')->columns(4)->schema([
                TextEntry::make('number')->label('Numero'),
                TextEntry::make('status')->label('Stato')->badge()
                    ->formatStateUsing(fn (ReservationStatus $state) => Labels::status($state))
                    ->color(fn (ReservationStatus $state) => Labels::statusColor($state)),
                TextEntry::make('channel')->label('Canale'),
                TextEntry::make('created_at')->label('Creata il')->dateTime('d/m/Y H:i', 'Europe/Rome'),
                TextEntry::make('starts_at')->label('Consegna')->dateTime('d/m/Y H:i', 'Europe/Rome'),
                TextEntry::make('ends_at')->label('Ritiro')->dateTime('d/m/Y H:i', 'Europe/Rome'),
                TextEntry::make('billable_days')->label('Giorni'),
                TextEntry::make('referral')->label('Provenienza')->placeholder('—'),
            ]),
            Section::make('Cliente e veicolo')->columns(4)->schema([
                TextEntry::make('customer.first_name')->label('Nome')
                    ->formatStateUsing(fn (Reservation $record) => $record->owner()->first_name.' '.$record->owner()->last_name),
                TextEntry::make('customer.email')->label('Email')->copyable(),
                TextEntry::make('customer.phone')->label('Telefono')->copyable(),
                TextEntry::make('plate')->label('Targa'),
                TextEntry::make('vehicle_make')->label('Veicolo')->placeholder('—')
                    ->formatStateUsing(fn (Reservation $record) => trim($record->vehicle_make.' '.$record->vehicle_model)),
                TextEntry::make('outbound_flight')->label('Voli')->placeholder('—')
                    ->formatStateUsing(fn (Reservation $record) => ($record->outbound_flight ?? '—').' / '.($record->return_flight ?? '—')),
                TextEntry::make('passengers')->label('Passeggeri'),
                TextEntry::make('notes')->label('Note')->placeholder('—'),
            ]),
            Section::make('Importi e pagamento')->columns(4)->schema([
                RepeatableEntry::make('lines')->label('Righe')->columnSpanFull()->table([
                    TableColumn::make('Voce'),
                    TableColumn::make('Q.tà'),
                    TableColumn::make('Importo'),
                ])->schema([
                    TextEntry::make('description'),
                    TextEntry::make('quantity'),
                    TextEntry::make('total_cents')->formatStateUsing(fn (ReservationLine $record) => Euro::format($record->total_cents)),
                ]),
                TextEntry::make('subtotal_cents')->label('Subtotale')->formatStateUsing(fn (int $state) => Euro::format($state)),
                TextEntry::make('discount_cents')->label('Sconto')
                    ->formatStateUsing(fn (Reservation $record) => $record->discount_cents > 0
                        ? '−'.Euro::format($record->discount_cents).' ('.($record->discount['code'] ?? '').')'
                        : '—'),
                TextEntry::make('total_cents')->label('Totale')->weight('bold')->formatStateUsing(fn (int $state) => Euro::format($state)),
                TextEntry::make('payment_method')->label('Pagamento')
                    ->formatStateUsing(fn (Reservation $record) => Labels::paymentMethod($record->payment_method).' · '.ReservationPresenter::paymentStatus($record)),
                TextEntry::make('payment_due_by')->label('Da pagare entro')->dateTime('d/m/Y H:i', 'Europe/Rome')->placeholder('—'),
                TextEntry::make('paid_at')->label('Pagata il')->dateTime('d/m/Y H:i', 'Europe/Rome')->placeholder('—'),
                TextEntry::make('email_sent')->label('Email di conferma')->formatStateUsing(fn (bool $state) => $state ? 'Inviata' : 'Non inviata'),
                TextEntry::make('receipt_path')->label('Ricevuta')->placeholder('—'),
            ]),
            Section::make('Cancellazione e rimborso')->columns(4)
                ->visible(fn (Reservation $record) => $record->cancelled_at !== null || $record->refund !== null)
                ->schema([
                    TextEntry::make('cancelled_at')->label('Annullata il')->dateTime('d/m/Y H:i', 'Europe/Rome')->placeholder('—'),
                    TextEntry::make('cancellation_reason')->label('Motivo')->placeholder('—'),
                    TextEntry::make('refund.status')->label('Rimborso')->formatStateUsing(fn (?string $state) => Labels::refund($state))->placeholder('—'),
                    TextEntry::make('refund.tax_code')->label('Codice fiscale')->placeholder('—'),
                    TextEntry::make('refund.error')->label('Errore del rimborso')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('customer'))
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('N.')->sortable()->searchable(),
                TextColumn::make('status')->label('Stato')->badge()
                    ->formatStateUsing(fn (ReservationStatus $state) => Labels::status($state))
                    ->color(fn (ReservationStatus $state) => Labels::statusColor($state)),
                TextColumn::make('starts_at')->label('Consegna')->dateTime('d/m/Y H:i', 'Europe/Rome')->sortable(),
                TextColumn::make('ends_at')->label('Ritiro')->dateTime('d/m/Y H:i', 'Europe/Rome')->sortable(),
                TextColumn::make('customer.last_name')->label('Cliente')
                    ->formatStateUsing(fn (Reservation $record) => $record->owner()->first_name.' '.$record->owner()->last_name)
                    ->searchable(['first_name', 'last_name', 'email']),
                TextColumn::make('plate')->label('Targa')->searchable(),
                TextColumn::make('total_cents')->label('Totale')->formatStateUsing(fn (int $state) => Euro::format($state))->alignEnd(),
                TextColumn::make('payment_method')->label('Pagamento')->formatStateUsing(fn (Reservation $record) => Labels::paymentMethod($record->payment_method)),
                TextColumn::make('channel')->label('Canale')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Stato')->options(Labels::statuses())->multiple(),
                Filter::make('period')->label('Periodo di sosta')
                    ->schema([
                        DatePicker::make('from')->label('In parcheggio dal'),
                        DatePicker::make('to')->label('al'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $from) => $q->where('end_date', '>=', $from))
                        ->when($data['to'] ?? null, fn (Builder $q, string $to) => $q->where('start_date', '<=', $to))),
                Filter::make('refund_pending')->label('Rimborsi da fare')
                    ->query(fn (Builder $query) => $query->whereIn('refund->status', ['requested', 'failed'])),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservations::route('/'),
            'view' => ViewReservation::route('/{record}'),
            'edit' => EditReservation::route('/{record}/edit'),
        ];
    }
}
