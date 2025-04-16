<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationLabel = '商品管理';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            Forms\Components\Card::make()
                ->schema([
                    Forms\Components\Section::make('基本情報')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('商品名')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('例：チョコレートケーキ')
                                ->live()
                                ->afterStateUpdated(function (string $state, Forms\Set $set) {
                                    $set('slug', $state);
                                }),

                            Forms\Components\Textarea::make('description')
                                ->label('商品概要')
                                ->maxLength(500)
                                ->placeholder('商品の説明を入力してください'),

                            Forms\Components\Hidden::make('slug')
                                ->reactive(),
                        ]),

                    Forms\Components\Section::make('アレルギー情報')
                        ->description('アレルギー品目を入力してください')
                        ->schema([
                            Forms\Components\TagsInput::make('allergens')
                                ->label('')
                                ->suggestions([
                                    '卵' => '卵',
                                    '乳' => '乳',
                                    '小麦' => '小麦',
                                    'えび' => 'えび',
                                    'かに' => 'かに',
                                    '落花生' => '落花生',
                                    'そば' => 'そば',
                                ]),
                        ])
                        ->columnSpan('full'),

                    Forms\Components\Section::make('在庫・価格情報')
                        ->schema([
                            Forms\Components\TextInput::make('price')
                                ->label('価格')
                                ->prefix('¥')
                                ->default(0)
                                ->minValue(0)
                                ->numeric()
                                ->required(),

                            Forms\Components\TextInput::make('stock')
                                ->label('在庫数')
                                ->suffix('個')
                                ->default(0)
                                ->minValue(0)
                                ->numeric()
                                ->required(),
                        ])
                        ->columns(2),

                    Forms\Components\Section::make('商品画像')
                        ->schema([
                            Forms\Components\FileUpload::make('image')
                                ->label('商品画像')
                                ->helperText('WebP形式に最適化されます。')
                                ->image()
                                ->directory('products')
                                ->imageEditor()
                                ->nullable()
                                ->disk('public')
                                ->preserveFilenames()
                                ->optimize('webp'),
                        ]),

                    Forms\Components\Section::make('オプション設定')
                        ->schema([
                            Forms\Components\Repeater::make('options')
                                ->relationship('options')
                                ->default([])
                                ->label('オプション')
                                ->collapsible()
                                ->itemLabel(fn (?array $state = null): string => $state
                                    ? (($state['option_name'] ?? 'オプション').' - ¥'.($state['price'] ?? ''))
                                    : 'オプション'
                                )
                                ->schema([
                                    Forms\Components\TextInput::make('option_name')
                                        ->label('オプション名')
                                        ->required()
                                        ->placeholder('例：サイズアップ')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('price')
                                        ->label('追加料金')
                                        ->prefix('¥')
                                        ->default(0)
                                        ->minValue(0)
                                        ->numeric(),
                                ])
                                ->live()
                                ->columns(2)
                                ->minItems(0)
                                ->createItemButtonLabel('オプションを追加'),
                        ]),
                ])
                ->columnSpan('full'),
        ]);
    }

    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('商品画像')
                    ->size(50)
                    ->rounded()
                    ->placeholder('No image'),
                Tables\Columns\TextColumn::make('name')
                    ->label('商品名')
                    ->sortable()
                    ->searchable()
                    ->extraAttributes(['class' => 'font-semibold text-lg text-gray-800']),
                Tables\Columns\TextColumn::make('price')
                    ->label('価格')
                    ->sortable()
                    ->extraAttributes(['class' => 'text-lg text-gray-700']),
                Tables\Columns\BadgeColumn::make('stock_status')
                    ->label('在庫ステータス')
                    ->getStateUsing(function ($record): string {
                        if ($record->stock <= 0) {
                            return '在庫切れ (0)';
                        }
                        if ($record->stock <= 5) {
                            return "残りわずか ({$record->stock})";
                        }

                        return "在庫あり ({$record->stock})";
                    })
                    ->colors([
                        'danger' => fn ($state): bool => str_contains($state, '在庫切れ'),
                        'warning' => fn ($state): bool => str_contains($state, '残りわずか'),
                        'success' => fn ($state): bool => str_contains($state, '在庫あり'),
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('登録日')
                    ->dateTime('Y年m月d日')
                    ->extraAttributes(['class' => 'text-sm text-gray-500']),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->image && Storage::disk('public')->exists($record->image)) {
                            Storage::disk('public')->delete($record->image);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->before(function (Collection $records) {
                        foreach ($records as $record) {
                            if ($record->image && Storage::disk('public')->exists($record->image)) {
                                Storage::disk('public')->delete($record->image);
                            }
                        }
                    }),
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
            'edit' => Pages\EditProduct::route('/{record:slug}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }
}
