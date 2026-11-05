<?php

namespace App\Engine\Filament\Resources\Customers\Pages;

use App\Engine\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomers extends ManageRecords
{
    protected static string $resource = CustomerResource::class;
}
