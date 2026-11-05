<?php

namespace App\Engine\Filament\Resources\ApiClients\Pages;

use App\Engine\Filament\Resources\ApiClients\ApiClientResource;
use App\Engine\Models\ApiClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageApiClients extends ManageRecords
{
    protected static string $resource = ApiClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issue')
                ->label('Nuovo client')
                ->icon('heroicon-o-plus')
                ->schema(ApiClientResource::fields(withChannel: true))
                ->action(function (array $data): void {
                    /** @var list<string> $origins */
                    $origins = $data['allowed_origins'] ?? [];
                    ['client' => $client, 'secret_key' => $secret] = ApiClient::issue(
                        (string) $data['name'],
                        (string) $data['channel'],
                        $origins,
                        filled($data['rate_limit_per_minute'] ?? null) ? (int) $data['rate_limit_per_minute'] : null,
                    );

                    Notification::make()
                        ->success()
                        ->title("Client \"{$client->name}\" creato")
                        ->body("Chiave segreta (copiala ora, non verrà più mostrata):\n{$secret}\n\nChiave pubblicabile:\n{$client->publishable_key}")
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
