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
        $this->calculateTotalPrice();
    }

    // 商品をカートに追加
    public function addToCart(int $productId): void
    {
        $product = Product::findOrFail($productId);

        // 在庫が0の場合は通知を表示
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
            'price' => $product->price,
            'quantity' => 1,
        ];

        $this->calculateTotalPrice();
        $this->updateCartSession();
    }

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

    public function removeFromCart(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
        $this->calculateTotalPrice();
        $this->updateCartSession();
    }

    public function calculateTotalPrice(): void
    {
        $this->totalPrice = collect($this->cart)->sum(function ($item) {
            return $item['price'] * (int)$item['quantity'];
        });
    }

    // セッションにカートデータを保存
    public function updateCartSession(): void
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
        if ($this->paymentAmount < $this->totalPrice) {
            Notification::make()
                ->title('支払い金額が不足しています。')
                ->danger()
                ->send();
            return;
        }
        
        // カート内の確認
        if (empty($this->cart)) {
            Notification::make()
                ->title('カートが空です。')
                ->danger()
                ->send();
            return;
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
                $product->reduceStock($item['quantity']);

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
