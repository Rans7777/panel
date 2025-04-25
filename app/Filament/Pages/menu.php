<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;

class menu extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.menu';

    protected static ?string $navigationLabel = 'メニュー';

    public function mount(): void
    {
        $this->redirect('/menu');
    }
}
