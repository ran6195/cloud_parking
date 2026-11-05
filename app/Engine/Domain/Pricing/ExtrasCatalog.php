<?php

namespace App\Engine\Domain\Pricing;

use App\Engine\Domain\Pricing\Exceptions\ExtraUnavailable;
use App\Engine\Domain\Pricing\Exceptions\IncompatibleExtras;

/**
 * Catalogo dei servizi aggiuntivi configurati dal gestore.
 */
final readonly class ExtrasCatalog
{
    /** @var array<string, ExtraDefinition> */
    private array $definitions;

    /**
     * @param  list<ExtraDefinition>  $definitions
     */
    public function __construct(array $definitions)
    {
        $indexed = [];
        foreach ($definitions as $definition) {
            $indexed[$definition->id->value] = $definition;
        }
        $this->definitions = $indexed;
    }

    /**
     * @return list<ExtraDefinition> servizi attivi, nell'ordine di ExtraId
     */
    public function active(): array
    {
        $active = array_values(array_filter($this->definitions, fn (ExtraDefinition $d) => $d->active));
        usort($active, fn (ExtraDefinition $a, ExtraDefinition $b) => $a->id->position() <=> $b->id->position());

        return $active;
    }

    /**
     * Definizione del servizio, anche se disattivato; null se non configurato.
     */
    public function find(ExtraId $id): ?ExtraDefinition
    {
        return $this->definitions[$id->value] ?? null;
    }

    public function get(ExtraId $id): ExtraDefinition
    {
        $definition = $this->definitions[$id->value] ?? null;

        if ($definition === null || ! $definition->active) {
            throw ExtraUnavailable::for($id);
        }

        return $definition;
    }

    /**
     * Valida la selezione e restituisce le definizioni senza duplicati, nell'ordine di ExtraId.
     *
     * @param  list<ExtraId>  $selection
     * @return list<ExtraDefinition>
     */
    public function resolve(array $selection): array
    {
        $unique = [];
        foreach ($selection as $id) {
            $unique[$id->value] = $id;
        }
        usort($unique, fn (ExtraId $a, ExtraId $b) => $a->position() <=> $b->position());

        $chosen = array_map($this->get(...), $unique);

        foreach ($chosen as $a) {
            foreach ($chosen as $b) {
                if (in_array($b->id, $a->incompatibleWith, true) || in_array($a->id, $b->incompatibleWith, true)) {
                    throw IncompatibleExtras::between($a, $b);
                }
            }
        }

        return $chosen;
    }
}
