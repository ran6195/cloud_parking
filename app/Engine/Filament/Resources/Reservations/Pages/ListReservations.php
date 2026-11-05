<?php

namespace App\Engine\Filament\Resources\Reservations\Pages;

use App\Engine\Filament\Resources\Reservations\ReservationResource;
use Filament\Resources\Pages\ListRecords;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;
}
