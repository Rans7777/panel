<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class OrderHistory extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = '注文履歴';

    public function mount(): void
    {
        $this->redirect('/order-history');
    }
}
