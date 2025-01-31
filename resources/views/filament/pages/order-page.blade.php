<x-filament::page>
    <div class="space-y-6">
        <h1 class="text-2xl font-bold mb-4">注文ページ</h1>

        {{-- 商品一覧 --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach (\App\Models\Product::all() as $product)
            <label class="w-full">
                <button type="button" 
                        wire:click="addToCart({{ $product->id }})" 
                        class="w-full border rounded-lg p-4 text-center transition hover:bg-gray-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                    <div class="flex flex-col items-center">
                        <img src="{{ $product->image ? asset('storage/' . $product->image) : asset('storage/no-image.png') }}" 
                             alt="{{ $product->name }}" 
                             class="h-32 w-32 object-cover rounded-lg">
                        <span class="block mt-2 font-bold">{{ $product->name }}</span>
                    </div>
                </button>
            </label>
            @endforeach
        </div>

        {{-- カート --}}
        <h2 class="text-xl font-bold mt-6">カート</h2>
        <table class="table-auto w-full border-collapse border border-gray-300 mt-4 text-center">
            <thead>
                <tr>
                    <th class="border p-2">商品名</th>
                    <th class="border p-2">単価</th>
                    <th class="border p-2">数量</th>
                    <th class="border p-2">小計</th>
                    <th class="border p-2">操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cart as $index => $item)
                    <tr>
                        <td class="border p-2">{{ $item['name'] }}</td>
                        <td class="border p-2">¥{{ number_format($item['price']) }}</td>
                        <td class="border p-2">
                            <input type="number" min="1" 
                                   wire:model.live="cart.{{ $index }}.quantity" 
                                   wire:change="updateQuantity({{ $index }}, $event.target.value)" 
                                   value="{{ $item['quantity'] }}" 
                                   class="w-16 border rounded text-center p-1 {{ config('filament.dark_mode') ? 'bg-gray-800 text-white' : 'bg-white text-black' }}">
                        </td>
                        <td class="border p-2">¥{{ number_format($item['price'] * $item['quantity']) }}</td>
                        <td class="border p-2">
                            <x-filament::button
                                color="danger"
                                wire:click="removeFromCart({{ $index }})"
                            >
                                削除
                            </x-filament::button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- 合計金額 --}}
        <div class="mt-4 text-right text-xl font-bold {{ config('filament.dark_mode') ? 'text-white' : 'text-black' }}">
            合計金額: ¥{{ number_format($totalPrice) }}
        </div>

        {{-- 確定ボタン --}}
        <div class="mt-6 text-right">
            <x-filament::button
                color="success"
                wire:click="showPaymentModal" 
            >
                注文を確定する
            </x-filament::button>
        </div>

        {{-- 支払いポップアップ --}}
        @if ($showPaymentPopup)
            <div class="fixed inset-0 bg-black/80 flex items-center justify-center z-50">
                <div 
                    class="
                    w-1/3 p-6 rounded-lg shadow-lg 
                    bg-white text-black
                    dark:bg-gray-900 dark:text-white
                    space-y-4
                    "
                >
                <h2 class="text-xl font-bold">支払い情報</h2>

                <div class="space-y-2">
                    <div>金額: ¥{{ number_format($totalPrice) }}</div>
                        <label class="block">
                            <span class="text-gray-600 dark:text-gray-400">お預かり金額</span>
                            <input
                                type="number"
                                wire:model.debounce.100ms="paymentAmount"
                                wire:change="calculateChange"
                                inputmode="numeric"
                                min="0"
                                class="
                                    mt-1 block w-full p-2 rounded border-gray-300
                                    focus:ring focus:ring-blue-500 focus:ring-opacity-50
                                    bg-white text-black
                                    dark:bg-gray-800 dark:text-white
                                "
                            >
                        </label>
                    <div>おつり: ¥{{ number_format($changeAmount) }}</div>
                </div>

                <div class="flex justify-between mt-4">
                    <button 
                        type="button"
                        wire:click="$set('showPaymentPopup', false)"
                        class="
                            px-4 py-2 rounded-lg
                            hover:bg-gray-300
                            focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500
                            bg-gray-100 text-black
                            dark:bg-gray-700 dark:text-white
                            dark:hover:bg-gray-600
                        "
                    >
                        キャンセル
                    </button>

                    <button 
                        type="button"
                        wire:click="confirmOrder"
                        class="
                            px-4 py-2 rounded-lg
                            hover:bg-gray-300
                            focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500
                            bg-gray-100 text-black
                            dark:bg-gray-700 dark:text-white
                            dark:hover:bg-gray-600
                        "
                    >
                        注文確定
                    </button>
                </div>
                </div>
            </div>
        @endif
    </div>
</x-filament::page>
