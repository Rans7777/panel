<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
    protected static ?string $title = '商品追加';

    public function mount(): void
    {
        if (!auth()->user()->hasRole('admin')) {
            Notification::make()
                ->warning()
                ->title('アクセス拒否')
                ->message('管理者権限が必要です。');
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
