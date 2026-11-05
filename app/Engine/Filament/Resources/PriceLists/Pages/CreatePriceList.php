<?php

namespace App\Engine\Filament\Resources\PriceLists\Pages;

use App\Engine\Filament\Resources\PriceLists\PriceListResource;
use App\Engine\Models\PriceList;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePriceList extends CreateRecord
{
    protected static string $resource = PriceListResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        ['attributes' => $attributes, 'cents' => $cents] = PriceListResource::splitFormData($data);

        $priceList = PriceList::query()->create($attributes + ['source' => PriceList::SOURCE_MANUAL]);
        $priceList->syncPrices($cents);

        return $priceList;
    }
}
