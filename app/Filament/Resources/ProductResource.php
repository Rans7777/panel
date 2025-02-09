<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('商品名')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('price')
                ->label('価格')
                ->numeric()
                ->required(),

            Forms\Components\TextInput::make('stock')
                ->label('在庫数')
                ->numeric()
                ->required(),

            Forms\Components\FileUpload::make('image')
                ->label('商品画像')
                ->image()
                ->directory('products')
                ->imageEditor()
                ->nullable()
                ->disk('public'),

            Forms\Components\Repeater::make('options')
                ->relationship('options')
                ->label('オプション')
                ->collapsible()
                ->itemLabel(fn (?array $state = null): string => $state
                    ? (($state['option_name'] ?? 'オプション') . ' - ' . ($state['price'] ?? ''))
                    : 'オプション'
                )
                ->schema([
                    Forms\Components\TextInput::make('option_name')
                        ->label('オプション名')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('price')
                        ->label('値段')
                        ->numeric()
                        ->required(),
                ])
                ->columns(2)
                ->minItems(0)
                ->createItemButtonLabel('オプションを追加'),
        ]);
    }

    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table->columns([
            Tables\Columns\ImageColumn::make('image')->label('商品画像')->size(50)->placeholder('No image'),
            Tables\Columns\TextColumn::make('name')->label('商品名')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('price')->label('価格')->sortable(),
            Tables\Columns\TextColumn::make('stock')->label('在庫数')->sortable(),
            Tables\Columns\TextColumn::make('created_at')->label('登録日')->dateTime(),
        ])
        ->actions([
            EditAction::make(),
            DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
