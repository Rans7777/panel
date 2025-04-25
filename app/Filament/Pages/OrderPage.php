<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;

final class OrderPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = '注文ページ';

    protected static ?int $navigationSort = 1;

    public function mount(): void
    {
        $this->redirect('/order');
    }
}
