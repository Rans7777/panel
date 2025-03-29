<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class menu extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.menu';

    protected static ?string $navigationLabel = 'メニュー';

    public function mount(): void
    {
        $this->redirect('/menu');
    }
}
