<?php

namespace App\Engine\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Risposta salvata per una Idempotency-Key, per client. Usata dalla fase 6.
 *
 * @property int $id
 * @property int $api_client_id
 * @property string $key
 * @property string $request_hash
 * @property int|null $response_status
 * @property array<string, mixed>|null $response_body
 * @property CarbonImmutable $expires_at
 */
class IdempotencyKey extends EngineModel
{
    protected $fillable = ['key', 'request_hash', 'response_status', 'response_body', 'expires_at'];

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'response_body' => 'array',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<ApiClient, $this>
     */
    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }
}
