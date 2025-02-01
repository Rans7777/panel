<?php
namespace App\Filament\Resources;

use App\Filament\Resources\OrdersResource\Pages;
use App\Models\Orders;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class OrdersResource extends Resource
{
    protected static ?string $model = Orders::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->label('商品名')
                ->relationship('product', 'name')
                ->required(),

            Forms\Components\TextInput::make('quantity')
                ->label('個数')
                ->numeric()
                ->minValue(1)
                ->required(),

            Forms\Components\TextInput::make('total_price')
                ->label('合計金額')
                ->numeric()
                ->required(),

            Forms\Components\DatePicker::make('order_date')
                ->label('注文日')
                ->required(),
        ]);
    }

    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table->columns([
        // 商品名カラム
        Tables\Columns\TextColumn::make('name')
            ->label('商品名')
            ->sortable(),

        // 個数カラム
        Tables\Columns\TextColumn::make('quantity')
            ->label('個数')
            ->sortable(),

        // 商品画像カラム
        Tables\Columns\ImageColumn::make('image')
            ->label('商品画像')
            ->size(50)
            ->sortable()
            ->placeholder('No Image'),

        // 合計金額カラム
        Tables\Columns\TextColumn::make('total_price')
            ->label('合計金額')
            ->sortable()
            ->money('JPY'),

        // 注文日カラム
        Tables\Columns\TextColumn::make('created_at')
            ->label('注文日')
            ->sortable()
            ->date('M d, Y'),
        ])
        ->filters([
            Tables\Filters\Filter::make('name')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('商品名'),
                ])
                ->query(function ($query, array $data): mixed {
                    return $query->when(
                        $data['name'],
                        fn ($query, $name) => $query->where('name', 'like', "%{$name}%")
                    );
                }),

            Tables\Filters\Filter::make('order_date')
                ->form([
                    Forms\Components\DatePicker::make('from')
                        ->label('開始日'),
                    Forms\Components\DatePicker::make('until')
                        ->label('終了日'),
                ])
                ->query(function ($query, array $data): mixed {
                    return $query
                        ->when(
                            $data['from'],
                            fn ($query) => $query->whereDate('order_date', '>=', $data['from']),
                        )
                        ->when(
                            $data['until'],
                            fn ($query) => $query->whereDate('order_date', '<=', $data['until']),
                        );
                })
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
