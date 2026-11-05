<?php

namespace App\Engine\Filament\Resources\Reservations\Pages;

use App\Engine\Filament\Resources\Reservations\ReservationResource;
use Filament\Resources\Pages\EditRecord;

class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
