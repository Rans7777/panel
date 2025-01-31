<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrdersResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrdersResource::class;
}
