<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;

final class OrderPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = '注文ページ';
    public function mount(): void
    {
        $this->redirect('/order');
    }
}
