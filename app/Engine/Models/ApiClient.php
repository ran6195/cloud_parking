<?php

namespace App\Engine\Models;

use Carbon\CarbonImmutable;
use Database\Engine\Factories\ApiClientFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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

    /** Cache delle origini consentite da tutti i client attivi (per le richieste CORS di preflight). */
    public const ORIGINS_CACHE_KEY = 'engine.api_clients.origins';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::ORIGINS_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::ORIGINS_CACHE_KEY));
    }

    /**
     * Crea un client con una nuova coppia di chiavi. La chiave segreta è
     * restituita solo qui: nel database ne resta l'hash.
     *
     * @param  list<string>  $allowedOrigins
     * @return array{client: self, secret_key: string}
     */
    public static function issue(string $name, string $channel, array $allowedOrigins = [], ?int $rateLimitPerMinute = null): array
    {
        $secret = 'sk_'.Str::random(48);

        $client = self::query()->create([
            'name' => $name,
            'channel' => $channel,
            'publishable_key' => 'pk_'.Str::random(32),
            'secret_key_hash' => self::hashSecret($secret),
            'allowed_origins' => array_map(self::normalizeOrigin(...), $allowedOrigins),
            'abilities' => [],
            'rate_limit_per_minute' => $rateLimitPerMinute,
        ]);

        return ['client' => $client, 'secret_key' => $secret];
    }

    /**
     * Trova il client attivo di una chiave pk_ o sk_.
     */
    public static function findByKey(string $key): ?self
    {
        $query = self::query()->whereNull('revoked_at');

        return match (true) {
            str_starts_with($key, 'pk_') => $query->where('publishable_key', $key)->first(),
            str_starts_with($key, 'sk_') => $query->where('secret_key_hash', self::hashSecret($key))->first(),
            default => null,
        };
    }

    /**
     * Origini consentite da almeno un client attivo.
     *
     * @return list<string>
     */
    public static function allAllowedOrigins(): array
    {
        /** @var list<string> */
        return Cache::remember(self::ORIGINS_CACHE_KEY, 300, fn () => self::query()
            ->whereNull('revoked_at')
            ->pluck('allowed_origins')
            ->flatten()
            ->unique()
            ->values()
            ->all());
    }

    /**
     * "https://Esempio.it/" → "https://esempio.it"
     */
    public static function normalizeOrigin(string $origin): string
    {
        return rtrim(mb_strtolower(trim($origin)), '/');
    }

    public function allowsOrigin(?string $origin): bool
    {
        return $origin !== null && in_array(self::normalizeOrigin($origin), $this->allowed_origins, true);
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
