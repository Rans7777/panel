<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
    protected static ?string $title = '商品追加';

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return ProductResource::getUrl('index');
    }

    protected function afterCreate(): void
    {
        activity()
            ->useLog('info')
            ->withProperties(['ip_address' => request()->ip()])
            ->log("商品『{$this->record->name}』が作成されました");
    }
}
