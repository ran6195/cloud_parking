<?php

namespace App\Engine\Filament\Pages;

use App\Engine\Application\QuoteSnapshot;
use App\Engine\Domain\Availability\AvailabilityChecker;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\PaymentMethod;
use App\Engine\Domain\Pricing\QuoteCalculator;
use App\Engine\Domain\Pricing\QuoteRequest;
use App\Engine\Domain\Pricing\StayPeriod;
use App\Engine\Domain\Shared\EngineException;
use App\Engine\Filament\Support\Labels;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Simulatore: lo stesso calcolo dell'API, con il dettaglio di listino, fallback e disponibilità.
 * Non salva nulla.
 *
 * @property-read Schema $form
 */
class QuoteSimulator extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Simulatore preventivo';

    protected static ?string $title = 'Simulatore preventivo';

    protected static ?string $slug = 'simulatore';

    protected static ?int $navigationSort = 6;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->form->fill(['payment_method' => PaymentMethod::OnSite->value, 'extras' => []]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(2)->components([
            DateTimePicker::make('start')->label('Consegna')->seconds(false)->required(),
            DateTimePicker::make('end')->label('Ritiro')->seconds(false)->required()->after('start'),
            CheckboxList::make('extras')->label('Servizi')->options(Labels::extras())->columns(2),
            Radio::make('payment_method')->label('Pagamento')->required()->options([
                PaymentMethod::OnSite->value => 'In parcheggio',
                PaymentMethod::Online->value => 'Online',
            ]),
            TextInput::make('discount_code')->label('Codice sconto')->maxLength(50),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('calculate')
                ->footer([Actions::make([Action::make('calculate')->label('Calcola')->submit('calculate')])]),
            Section::make('Risultato')
                ->visible(fn () => $this->result !== null || $this->error !== null)
                ->schema([View::make('engine::filament.quote-result')->viewData(fn () => ['result' => $this->result, 'error' => $this->error])]),
        ]);
    }

    public function calculate(QuoteCalculator $calculator, AvailabilityChecker $availability): void
    {
        /** @var array{start: string, end: string, extras?: list<string>, payment_method: string, discount_code?: string|null} $data */
        $data = $this->form->getState();
        $this->result = null;
        $this->error = null;

        try {
            $stay = StayPeriod::fromStrings($data['start'], $data['end']);
            $quote = $calculator->quote(new QuoteRequest(
                stay: $stay,
                extras: array_map(ExtraId::from(...), $data['extras'] ?? []),
                paymentMethod: PaymentMethod::from($data['payment_method']),
                discountCode: filled($data['discount_code'] ?? null) ? (string) $data['discount_code'] : null,
            ));
            $checked = $availability->check($stay, $quote->extras);
        } catch (EngineException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->result = [
            'stay' => QuoteSnapshot::stay($quote),
            'lines' => QuoteSnapshot::lines($quote),
            'subtotal_cents' => $quote->subtotal->cents,
            'total_cents' => $quote->total->cents,
            'discount' => QuoteSnapshot::discount($quote->discount),
            'trace' => QuoteSnapshot::trace($quote),
            'availability' => QuoteSnapshot::availability($checked),
        ];
    }
}
