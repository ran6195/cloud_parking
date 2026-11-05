<?php

namespace Database\Engine\Factories;

use App\Engine\Models\ApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiClient>
 */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    public function definition(): array
    {
        return [
            'name' => 'Form del CMS',
            'channel' => 'web',
            'publishable_key' => 'pk_'.Str::random(32),
            'secret_key_hash' => ApiClient::hashSecret('sk_'.Str::random(40)),
            'allowed_origins' => [],
            'abilities' => [],
        ];
    }
}
