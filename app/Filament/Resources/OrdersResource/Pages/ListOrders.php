<?php

namespace App\Filament\Resources\OrdersResource\Pages;

use App\Filament\Resources\OrdersResource;
use Filament\Pages\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrdersResource::class;
    protected static ?string $title = '注文一覧';

    public function mount(): void
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, '管理者権限が必要です。');
        }
        parent::mount();
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getActions(): array
    {
        return [
            CreateAction::make()->label('新規注文追加'),
        ];
    }
}
