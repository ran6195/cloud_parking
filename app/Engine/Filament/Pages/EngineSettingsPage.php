<?php

namespace App\Engine\Filament\Pages;

use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Filament\Support\Euro;
use App\Engine\Filament\Support\Labels;
use App\Engine\Settings\EngineSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Impostazioni del gestore (tabella engine.settings, letta da EngineSettings).
 *
 * @property-read Schema $form
 */
class EngineSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Impostazioni';

    protected static ?string $title = 'Impostazioni del motore';

    protected static ?string $slug = 'impostazioni';

    protected static ?int $navigationSort = 9;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Campi modificabili per ciascun servizio, in base al tipo di prezzo. */
    private const EXTRA_FIELDS = [
        'covered_parking' => ['active', 'name', 'description', 'percentage', 'capacity'],
        'exterior_wash' => ['active', 'name', 'description', 'price'],
        'interior_wash' => ['active', 'name', 'description', 'price'],
        'key_drop' => ['active', 'name', 'description', 'price', 'capacity'],
        'ev_charging' => ['active', 'name', 'description', 'message'],
        'tyre_repair' => ['active', 'name', 'description', 'message'],
    ];

    public function mount(EngineSettings $settings): void
    {
        $extras = [];
        foreach (ExtraId::cases() as $id) {
            $values = $settings->extra($id);
            $extras[$id->value] = [
                'active' => (bool) ($values['active'] ?? false),
                'name' => $values['name'] ?? null,
                'description' => $values['description'] ?? null,
                'percentage' => $values['percentage'] ?? null,
                'price' => Euro::fromCents(isset($values['price_cents']) ? (int) $values['price_cents'] : null),
                'capacity' => $values['capacity'] ?? null,
                'message' => $values['message'] ?? null,
            ];
        }

        $this->form->fill([
            'total_spots' => $settings->totalSpots(),
            'auto_block' => $settings->autoBlock(),
            'on_site_fee' => Euro::fromCents($settings->paymentFees()->onSite->cents),
            'online_window_minutes' => $settings->onlinePaymentWindowMinutes(),
            'cancellation_hours' => $settings->cancellationHours(),
            'refunds_mode' => $settings->automaticRefunds() ? 'automatic' : 'manual',
            'fallback_years' => $settings->fallbackYears(),
            'brand_name' => $settings->brandName(),
            'contact_phone' => $settings->contactPhone(),
            'contact_email' => $settings->managerEmail(),
            'extras' => $extras,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $extraTabs = [];
        foreach (ExtraId::cases() as $id) {
            $fields = self::EXTRA_FIELDS[$id->value];
            $path = "extras.{$id->value}";

            $extraTabs[] = Tab::make(Labels::extras()[$id->value])->columns(2)->schema(array_values(array_filter([
                Toggle::make("{$path}.active")->label('Attivo')->columnSpanFull(),
                TextInput::make("{$path}.name")->label('Nome')->required()->maxLength(100),
                TextInput::make("{$path}.description")->label('Descrizione')->maxLength(255),
                in_array('percentage', $fields, true) ? TextInput::make("{$path}.percentage")->label('Supplemento sul parcheggio')->integer()->minValue(0)->maxValue(200)->suffix('%')->required() : null,
                in_array('price', $fields, true) ? TextInput::make("{$path}.price")->label('Prezzo')->numeric()->minValue(0)->step(0.01)->prefix('€')->required() : null,
                in_array('capacity', $fields, true) ? TextInput::make("{$path}.capacity")->label('Posti disponibili')->integer()->minValue(0)->required()->helperText('0 = servizio non prenotabile.') : null,
                in_array('message', $fields, true) ? TextInput::make("{$path}.message")->label('Messaggio al cliente')->maxLength(255)->columnSpanFull() : null,
            ])));
        }

        return $schema->statePath('data')->components([
            Section::make('Parcheggio')->columns(2)->schema([
                TextInput::make('total_spots')->label('Posti totali')->integer()->minValue(0)->required(),
                Toggle::make('auto_block')->label('Blocco automatico')->helperText('Rifiuta le prenotazioni quando i posti sono esauriti.')->inline(false),
            ]),
            Section::make('Pagamenti e cancellazioni')->columns(2)->schema([
                TextInput::make('on_site_fee')->label('Commissione pagamento in parcheggio')->numeric()->minValue(0)->step(0.01)->prefix('€')->required(),
                TextInput::make('online_window_minutes')->label('Tempo per pagare online')->integer()->minValue(5)->maxValue(240)->suffix('minuti')->required(),
                TextInput::make('cancellation_hours')->label('Cancellazione gratuita fino a')->integer()->minValue(0)->suffix('ore prima della consegna')->required(),
                Radio::make('refunds_mode')->label('Rimborsi')->required()->options([
                    'manual' => 'Manuali: il gestore rimborsa da PayPal',
                    'automatic' => 'Automatici su PayPal',
                ]),
                TextInput::make('fallback_years')->label('Listino mancante: cerca negli anni precedenti fino a')->integer()->minValue(0)->maxValue(10)->suffix('anni')->required(),
            ]),
            Section::make('Contatti ed email')->columns(3)->schema([
                TextInput::make('brand_name')->label('Nome nelle email')->required()->maxLength(100),
                TextInput::make('contact_phone')->label('Telefono per i clienti')->required()->maxLength(30),
                TextInput::make('contact_email')->label('Email del gestore')->email()->helperText('Riceve gli avvisi di prenotazioni e cancellazioni.'),
            ]),
            Section::make('Servizi aggiuntivi')->schema([Tabs::make('extras')->tabs($extraTabs)]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Salva')->submit('save')])]),
        ]);
    }

    public function save(EngineSettings $settings): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->form->getState();

        $settings->set('parking.total_spots', (int) $data['total_spots']);
        $settings->set('parking.auto_block', (bool) $data['auto_block']);
        $settings->set('payments.on_site_fee_cents', Euro::toCents($data['on_site_fee']));
        $settings->set('payments.online_window_minutes', (int) $data['online_window_minutes']);
        $settings->set('cancellation.free_hours_before_start', (int) $data['cancellation_hours']);
        $settings->set('refunds.mode', $data['refunds_mode'] === 'automatic' ? 'automatic' : 'manual');
        $settings->set('pricing.fallback_years', (int) $data['fallback_years']);
        $settings->set('brand.name', (string) $data['brand_name']);
        $settings->set('contact.phone', (string) $data['contact_phone']);
        $settings->set('contact.email', filled($data['contact_email'] ?? null) ? (string) $data['contact_email'] : null);

        /** @var array<string, array<string, mixed>> $extras */
        $extras = $data['extras'];
        foreach (self::EXTRA_FIELDS as $id => $fields) {
            $values = ['active' => (bool) ($extras[$id]['active'] ?? false), 'name' => (string) $extras[$id]['name'], 'description' => $extras[$id]['description'] ?? null];
            if (in_array('percentage', $fields, true)) {
                $values['percentage'] = (int) $extras[$id]['percentage'];
            }
            if (in_array('price', $fields, true)) {
                $values['price_cents'] = Euro::toCents($extras[$id]['price']);
            }
            if (in_array('capacity', $fields, true)) {
                $values['capacity'] = (int) $extras[$id]['capacity'];
            }
            if (in_array('message', $fields, true)) {
                $values['message'] = $extras[$id]['message'] ?? null;
            }
            $settings->set("extras.{$id}", $values);
        }

        Notification::make()->success()->title('Impostazioni salvate.')->send();
    }
}
