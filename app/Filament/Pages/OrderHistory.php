<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;

class OrderHistory extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = '注文履歴 (新)';

    protected static ?int $navigationSort = 3;

    public function mount(): void
    {
        $this->redirect('/order-history');
    }
}
