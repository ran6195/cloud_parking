<?php

namespace App\Engine\Settings;

use App\Engine\Domain\Availability\CapacityRules;
use App\Engine\Domain\Pricing\ExtraDefinition;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\ExtraPricing;
use App\Engine\Domain\Pricing\ExtrasCatalog;
use App\Engine\Domain\Pricing\PaymentFees;
use App\Engine\Domain\Shared\Money;
use App\Engine\Models\Setting;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Impostazioni del gestore, tipizzate e in cache (svuotata a ogni salvataggio di Setting).
 *
 * Ogni chiave ha un valore predefinito: quelli del legacy al 31/10/2026, così
 * il motore funziona anche con la tabella vuota. Per i servizi si salvano solo
 * i campi modificati, che si sovrappongono ai predefiniti.
 */
final class EngineSettings
{
    public const CACHE_KEY = 'engine.settings';

    public const DEFAULTS = [
        'parking.total_spots' => 285,
        'parking.auto_block' => true,
        'payments.on_site_fee_cents' => 100,
        'cancellation.free_hours_before_start' => 24,
        'pricing.fallback_years' => 5,
        'extras.covered_parking' => [
            'active' => true, 'name' => 'Posto coperto', 'description' => null,
            'percentage' => 40, 'capacity' => 3, 'message' => null,
        ],
        'extras.exterior_wash' => [
            'active' => false, 'name' => 'Lavaggio esterno', 'description' => null,
            'price_cents' => 1500, 'message' => null,
        ],
        'extras.interior_wash' => [
            'active' => false, 'name' => 'Pulizia abitacolo', 'description' => null,
            'price_cents' => 1500, 'message' => null,
        ],
        'extras.key_drop' => [
            'active' => true, 'name' => 'Tieni le chiavi', 'description' => null,
            'price_cents' => 700, 'capacity' => 3, 'message' => null,
        ],
        'extras.ev_charging' => [
            'active' => false, 'name' => 'Ricarica auto elettrica', 'description' => null,
            'message' => 'Il costo della ricarica dipenderà dal costo del kWh al momento della ricarica',
        ],
        'extras.tyre_repair' => [
            'active' => false, 'name' => 'Riparazione gomme', 'description' => null,
            'message' => 'Il costo della riparazione è di 20€ a chiodo',
        ],
    ];

    /** @var array<string, mixed>|null */
    private ?array $stored = null;

    public function get(string $key): mixed
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new InvalidArgumentException("Impostazione sconosciuta: [{$key}].");
        }

        $default = self::DEFAULTS[$key];
        $value = $this->stored()[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return is_array($default) && is_array($value) ? array_replace($default, $value) : $value;
    }

    public function set(string $key, mixed $value): void
    {
        $this->get($key);
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->stored = null;
    }

    public function totalSpots(): int
    {
        return $this->int('parking.total_spots');
    }

    public function autoBlock(): bool
    {
        return (bool) $this->get('parking.auto_block');
    }

    public function cancellationHours(): int
    {
        return $this->int('cancellation.free_hours_before_start');
    }

    public function fallbackYears(): int
    {
        return $this->int('pricing.fallback_years');
    }

    public function paymentFees(): PaymentFees
    {
        return new PaymentFees(Money::cents($this->int('payments.on_site_fee_cents')));
    }

    public function capacityRules(): CapacityRules
    {
        $capacities = [];
        foreach (ExtraId::cases() as $id) {
            if (self::structure($id)['capacity_limited']) {
                $capacities[$id->value] = $this->extraCapacity($id);
            }
        }

        return new CapacityRules($this->totalSpots(), $this->autoBlock(), $capacities);
    }

    /**
     * Catalogo dei servizi. Un servizio a capacità limitata con capacità 0 è
     * considerato disattivato, come nel legacy (coperto attivo se posti_coperti > 0).
     */
    public function catalog(): ExtrasCatalog
    {
        return new ExtrasCatalog(array_map($this->definition(...), ExtraId::cases()));
    }

    /**
     * @return array<string, mixed>
     */
    public function extra(ExtraId $id): array
    {
        /** @var array<string, mixed> */
        return $this->get('extras.'.$id->value);
    }

    private function definition(ExtraId $id): ExtraDefinition
    {
        $values = $this->extra($id);
        $structure = self::structure($id);
        $active = (bool) ($values['active'] ?? false)
            && (! $structure['capacity_limited'] || $this->extraCapacity($id) > 0);

        return new ExtraDefinition(
            id: $id,
            name: (string) $values['name'],
            pricing: $structure['pricing'],
            active: $active,
            price: $structure['pricing'] === ExtraPricing::Fixed ? Money::cents((int) $values['price_cents']) : null,
            percentage: $structure['pricing'] === ExtraPricing::PercentageOfParking ? (int) $values['percentage'] : null,
            capacityLimited: $structure['capacity_limited'],
            incompatibleWith: $structure['incompatible_with'],
            description: isset($values['description']) ? (string) $values['description'] : null,
            message: isset($values['message']) ? (string) $values['message'] : null,
        );
    }

    private function extraCapacity(ExtraId $id): int
    {
        return (int) ($this->extra($id)['capacity'] ?? 0);
    }

    /**
     * Regole fisse dei servizi, non modificabili dal gestore.
     *
     * @return array{pricing: ExtraPricing, capacity_limited: bool, incompatible_with: list<ExtraId>}
     */
    private static function structure(ExtraId $id): array
    {
        return match ($id) {
            ExtraId::CoveredParking => ['pricing' => ExtraPricing::PercentageOfParking, 'capacity_limited' => true, 'incompatible_with' => [ExtraId::KeyDrop]],
            ExtraId::KeyDrop => ['pricing' => ExtraPricing::Fixed, 'capacity_limited' => true, 'incompatible_with' => [ExtraId::CoveredParking]],
            ExtraId::ExteriorWash, ExtraId::InteriorWash => ['pricing' => ExtraPricing::Fixed, 'capacity_limited' => false, 'incompatible_with' => []],
            ExtraId::EvCharging, ExtraId::TyreRepair => ['pricing' => ExtraPricing::Informational, 'capacity_limited' => false, 'incompatible_with' => []],
        };
    }

    private function int(string $key): int
    {
        return (int) $this->get($key);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        /** @var array<string, mixed> */
        return $this->stored ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );
    }
}
