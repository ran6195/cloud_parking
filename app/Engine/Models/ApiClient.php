<?php

namespace App\Engine\Models;

use Carbon\CarbonImmutable;
use Database\Engine\Factories\ApiClientFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Interfaccia che usa l'API (form del CMS, PWA, partner), con il proprio canale.
 * La chiave segreta non è mai salvata in chiaro: solo il suo sha256.
 *
 * @property int $id
 * @property string $name
 * @property string $channel
 * @property string $publishable_key
 * @property string $secret_key_hash
 * @property list<string> $allowed_origins
 * @property list<string> $abilities
 * @property int|null $rate_limit_per_minute
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $revoked_at
 */
#[UseFactory(ApiClientFactory::class)]
class ApiClient extends EngineModel
{
    /** @use HasFactory<ApiClientFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'channel', 'publishable_key', 'secret_key_hash',
        'allowed_origins', 'abilities', 'rate_limit_per_minute', 'revoked_at',
    ];

    protected $hidden = ['secret_key_hash'];

    protected function casts(): array
    {
        return [
            'allowed_origins' => 'array',
            'abilities' => 'array',
            'rate_limit_per_minute' => 'integer',
            'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * @return HasMany<WebhookEndpoint, $this>
     */
    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }
}
