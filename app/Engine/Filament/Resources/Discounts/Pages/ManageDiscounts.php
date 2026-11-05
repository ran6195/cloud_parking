<?php

namespace App\Engine\Filament\Resources\Discounts\Pages;

use App\Engine\Filament\Resources\Discounts\DiscountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDiscounts extends ManageRecords
{
    protected static string $resource = DiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(DiscountResource::dehydrate(...))];
    }
}
