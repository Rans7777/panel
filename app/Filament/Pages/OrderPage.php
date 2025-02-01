<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use App\Models\Product;
use App\Models\Orders;
use Illuminate\Support\Facades\DB;

class OrderPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static string $view = 'filament.pages.order-page';

    public array $cart = [];
    public int $totalPrice = 0;
    public bool $showPaymentPopup = false;
    public int $paymentAmount = 0;
    public int $changeAmount = 0;

    public function mount(): void
    {
        $this->cart = session('cart', []);
        $this->syncCartWithDatabase();
        $this->calculateTotalPrice();
    }

    // 商品をカートに追加
    public function addToCart(int $productId): void
    {
        $product = Product::findOrFail($productId);

        if ($product->stock <= 0) {
            Notification::make()
                ->title('在庫がありません: ' . $product->name)
                ->danger()
                ->send();
            return;
        }

        foreach ($this->cart as &$item) {
            if ($item['id'] === $product->id) {
                $item['quantity']++;
                $this->calculateTotalPrice();
                $this->updateCartSession();
                return;
            }
        }

        $this->cart[] = [
            'id' => $product->id,
            'name' => $product->name,
            'image' => $product->image,
            'price' => $product->price,
            'quantity' => 1,
        ];

        $this->calculateTotalPrice();
        $this->updateCartSession();
    }

    // カート内の指定した商品の数量を更新
    public function updateQuantity(int $index, int $quantity): void
    {
        if ($quantity <= 0) {
            unset($this->cart[$index]);
        } else {
            $this->cart[$index]['quantity'] = $quantity;
        }

        $this->calculateTotalPrice();
        $this->updateCartSession();
    }

    // カートから指定した商品を削除
    public function removeFromCart(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
        $this->calculateTotalPrice();
        $this->updateCartSession();
    }

    // カート内の商品情報を最新の状態に同期する
    private function syncCartWithDatabase(): void
    {
        foreach ($this->cart as $index => $item) {
            $product = Product::find($item['id']);
            if ($product) {
                $this->cart[$index]['name'] = $product->name;
                $this->cart[$index]['image'] = $product->image;
                $this->cart[$index]['price'] = $product->price;
            }
        }
        $this->updateCartSession();
    }

    // カート内の商品の合計金額を計算
    private function calculateTotalPrice(): void
    {
        $this->totalPrice = collect($this->cart)->sum(function ($item) {
            return $item['price'] * (int)$item['quantity'];
        });
    }

    // セッションにカートデータを保存
    private function updateCartSession(): void
    {
        session(['cart' => $this->cart]);
    }

    // 支払いポップアップを開く
    public function showPaymentModal(): void
    {
        $this->paymentAmount = 0;
        $this->changeAmount = 0;
        $this->showPaymentPopup = true;
    }

    // おつりを計算
    public function calculateChange(): void
    {
        $this->changeAmount = (int)$this->paymentAmount - $this->totalPrice;
    }

    // 注文を確定
    public function confirmOrder(): void
    {
        $this->syncCartWithDatabase();

        if ($this->paymentAmount < $this->totalPrice) {
            Notification::make()
                ->title('支払い金額が不足しています。')
                ->danger()
                ->send();
            return;
        }
        
        if (empty($this->cart)) {
            Notification::make()
                ->title('カートが空です。')
                ->danger()
                ->send();
            return;
        }
        
        // 注文数量が在庫よりも上回っているか検証する
        foreach ($this->cart as $item) {
            $product = Product::find($item['id']);
            if ($product && $product->stock < $item['quantity']) {
                $this->showPaymentPopup = false;
                Notification::make()
                    ->title('注文の数量が在庫を超えています： ' . $product->name)
                    ->danger()
                    ->send();
                return;
            }
        }
        
        // トランザクション内で処理
        DB::transaction(function () {
            foreach ($this->cart as $item) {
                // 商品を取得
                $product = Product::find($item['id']);

                if (!$product) {
                    throw new \Exception('商品が存在しません。');
                }

                // 在庫を減少させる
                $product->decrement('stock', $item['quantity']);

                // 注文を保存
                Orders::create([
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'image' => $item['image'] ?? null,
                    'total_price' => $item['price'] * $item['quantity'],
                ]);
            }
        });

        // カート内を空にする
        $this->cart = [];
        $this->showPaymentPopup = false;
        $this->calculateTotalPrice();
        session()->forget('cart');

        Notification::make()
            ->title('注文が確定しました！')
            ->success()
            ->send();
    }
    
    protected function getActions(): array
    {
        return [];
    }
}
