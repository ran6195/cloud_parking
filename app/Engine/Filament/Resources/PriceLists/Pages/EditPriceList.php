<?php

namespace App\Engine\Filament\Resources\PriceLists\Pages;

use App\Engine\Filament\Resources\PriceLists\PriceListResource;
use App\Engine\Filament\Support\Euro;
use App\Engine\Models\PriceList;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPriceList extends EditRecord
{
    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var PriceList $record */
        $record = $this->getRecord();

        $data['prices'] = array_map(fn (int $cents) => Euro::fromCents($cents), $record->centsByDays());
        $data['extra_day'] = Euro::fromCents($record->extra_day_cents);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var PriceList $record */
        ['attributes' => $attributes, 'cents' => $cents] = PriceListResource::splitFormData($data);

        $record->update($attributes);
        $record->syncPrices($cents);

        return $record;
    }
}
