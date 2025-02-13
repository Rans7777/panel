<?php

namespace App\Filament\Resources\OrdersResource\Pages;

use App\Filament\Resources\OrdersResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrdersResource::class;
    protected static ?string $title = '注文追加';

    public function mount(): void
    {
        if (!auth()->user()->hasRole('admin')) {
            Notification::make()
                ->warning()
                ->title('アクセス拒否')
                ->body('管理者権限が必要です。')
                ->send();
            throw new HttpResponseException(new RedirectResponse('/admin/'));
        }
        parent::mount();
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return OrdersResource::getUrl('index');
    }
}
