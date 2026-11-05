<?php

namespace App\Engine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * URL a cui un client riceve gli eventi (reservation.created, …), firmati con $secret.
 *
 * @property int $id
 * @property int $api_client_id
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property bool $active
 */
class WebhookEndpoint extends EngineModel
{
    protected $fillable = ['url', 'secret', 'events', 'active'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'active' => 'boolean',
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
