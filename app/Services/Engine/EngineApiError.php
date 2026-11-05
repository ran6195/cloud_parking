<?php

namespace App\Services\Engine;

use RuntimeException;

/**
 * Errore applicativo del motore (problem+json 4xx): ha un "code" stabile e,
 * per i dati non validi, i messaggi per campo.
 */
class EngineApiError extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $problem
     */
    public function __construct(public readonly int $status, public readonly array $problem)
    {
        parent::__construct((string) ($problem['detail'] ?? $problem['title'] ?? 'Errore del motore prenotazioni.'));
    }

    public function errorCode(): string
    {
        return (string) ($this->problem['code'] ?? 'unknown');
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        /** @var array<string, list<string>> */
        return is_array($this->problem['errors'] ?? null) ? $this->problem['errors'] : [];
    }
}
