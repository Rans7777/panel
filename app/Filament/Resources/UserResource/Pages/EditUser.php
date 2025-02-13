<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;

final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'ユーザーの編集';

    public function mount(int|string $record): void
    {
        if (!auth()->user()->hasRole('admin')) {
            Notification::make()
                ->warning()
                ->title('アクセス拒否')
                ->body('管理者権限が必要です。')
                ->send();
            throw new HttpResponseException(new RedirectResponse('/admin/'));
        }
        parent::mount($record);
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var \App\Models\User $user */
        $user = $this->record;
        activity()
            ->useLog('info')
            ->withProperties(['ip_address' => request()->ip()])
            ->log("ユーザー '{$user->name}' が編集されました");
    }
}
