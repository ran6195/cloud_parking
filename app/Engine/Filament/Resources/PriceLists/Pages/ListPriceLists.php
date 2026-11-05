<?php

namespace App\Engine\Filament\Resources\PriceLists\Pages;

use App\Engine\Filament\Resources\PriceLists\PriceListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPriceLists extends ListRecords
{
    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
