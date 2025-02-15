<x-filament::page>
    <style>
        .pressed-active {
            transform: scale(0.95);
            transition: transform 0.2s ease;
        }
    </style>

    <!-- 商品一覧・カート・支払い画面 -->
    <div class="space-y-6">
        <h1 class="text-2xl font-bold mb-4">
            <div class="flex items-center">
                <img width="32" height="32"
                     src="https://img.icons8.com/color/32/add-shopping-cart--v1.png"
                     alt="注文アイコン"/>
                <span class="ml-2 text-2xl font-bold">注文ページ</span>
            </div>
        </h1>

        @php
            $products = \App\Models\Product::where('stock', '>', 0)->get();
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @forelse ($products as $product)
                <div>
                    <button type="button"
                        wire:click="handleProductClick({{ $product->id }})"
                        class="product-button w-full border rounded-lg p-4 text-center transition-transform duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 hover:bg-gray-800 hover:text-white"
                        x-on:mousedown="$el.classList.add('pressed-active')"
                        x-on:mouseup="$el.classList.remove('pressed-active')"
                        x-on:mouseleave="$el.classList.remove('pressed-active')"
                        x-on:touchstart="$el.classList.add('pressed-active')"
                        x-on:touchend="$el.classList.remove('pressed-active')"
                        x-on:touchcancel="$el.classList.remove('pressed-active')"
                    >
                        <div class="flex flex-col items-center">
                            <img src="{{ $product->image ? asset('storage/' . $product->image) : asset('https://img.icons8.com/clouds/100/no-image.png') }}" 
                                 alt="{{ $product->name }}"
                                 class="h-32 w-32 object-cover rounded-lg">
                            <span class="block mt-2 font-bold truncate" title="{{ $product->name }}">
                                {{ $product->name }}
                            </span>
                        </div>
                    </button>
                </div>
            @empty
                <div class="col-span-full flex items-center justify-center space-x-2">
                    <img width="24" height="24" src="https://img.icons8.com/fluency/24/error.png" alt="error"/>
                    <p>利用可能な商品はありません。</p>
                </div>
            @endforelse
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
                @forelse ($cart as $index => $item)
                    <tr>
                        <td class="border p-2 break-words">
                            {{ $item['name'] }}
                            @if(isset($item['options']))
                                <div class="mt-1 text-sm text-gray-500">
                                    オプション:
                                    <ul>
                                        @foreach ($item['options'] as $option)
                                            <li>
                                                {{ $option['option_name'] }} (追加料金: ¥{{ number_format($option['price']) }})
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </td>
                        <td class="border p-2">¥{{ number_format($item['price']) }}</td>
                        <td class="border p-2">
                            <input type="number" min="1" 
                                wire:model.live="cart.{{ $index }}.quantity" 
                                wire:change="updateQuantity({{ $index }}, $event.target.value)" 
                                value="{{ $item['quantity'] }}" 
                                class="w-16 border rounded text-center p-1 bg-white text-black dark:bg-gray-800 dark:text-white"
                            >
                        </td>
                        <td class="border p-2">¥{{ number_format($item['price'] * (int)$item['quantity']) }}</td>
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

        {{-- 注文確定ボタン --}}
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
                <div class="w-1/3 p-6 rounded-lg shadow-lg bg-white text-black dark:bg-gray-900 dark:text-white space-y-4">
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
                                class="mt-1 block w-full p-2 rounded border-gray-300 focus:ring focus:ring-blue-500 focus:ring-opacity-50 bg-white text-black dark:bg-gray-800 dark:text-white"
                            >
                        </label>
                        <div>おつり: ¥{{ number_format($changeAmount) }}</div>
                    </div>
                    <div class="flex justify-between space-x-4">
                        <button type="button"
                            wire:click="$set('showPaymentPopup', false)"
                            class="border border-red-500 text-red-500 rounded-lg px-4 py-2 transition-transform duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 hover:bg-red-500 hover:text-white"
                            x-on:mousedown="$el.classList.add('pressed-active')"
                            x-on:mouseup="$el.classList.remove('pressed-active')"
                            x-on:mouseleave="$el.classList.remove('pressed-active')"
                            x-on:touchstart="$el.classList.add('pressed-active')"
                            x-on:touchend="$el.classList.remove('pressed-active')"
                            x-on:touchcancel="$el.classList.remove('pressed-active')"
                        >
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="24" height="24" viewBox="0 0 48 48">
                                  <path fill="#f44336" d="M44,24c0,11.045-8.955,20-20,20S4,35.045,4,24S12.955,4,24,4S44,12.955,44,24z"></path><path fill="#fff" d="M29.656,15.516l2.828,2.828l-14.14,14.14l-2.828-2.828L29.656,15.516z"></path><path fill="#fff" d="M32.484,29.656l-2.828,2.828l-14.14-14.14l2.828-2.828L32.484,29.656z"></path>
                                </svg>
                                <span class="ml-2">キャンセル</span>
                            </div>
                        </button>
                        <button type="button"
                            wire:click="confirmOrder"
                            class="border border-green-500 text-green-500 rounded-lg px-4 py-2 transition-transform duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 hover:bg-green-500 hover:text-white"
                            x-on:mousedown="$el.classList.add('pressed-active')"
                            x-on:mouseup="$el.classList.remove('pressed-active')"
                            x-on:mouseleave="$el.classList.remove('pressed-active')"
                            x-on:touchstart="$el.classList.add('pressed-active')"
                            x-on:touchend="$el.classList.remove('pressed-active')"
                            x-on:touchcancel="$el.classList.remove('pressed-active')"
                        >
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="24" height="24" viewBox="0 0 48 48">
                                  <path fill="#43A047" d="M40.6 12.1L17 35.7 7.4 26.1 4.6 29 17 41.3 43.4 14.9z"></path>
                                </svg>
                                <span class="ml-2">注文確定</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- オプション選択用ポップアップ --}}
        @if ($showOptionsPopup)
            <div class="fixed inset-0 bg-black/80 flex items-center justify-center z-50">
                <div class="w-1/3 p-6 rounded-lg shadow-lg bg-white text-black dark:bg-gray-900 dark:text-white space-y-4">
                    <h2 class="text-xl font-bold">オプション選択</h2>
                    <div class="space-y-2">
                        <p>この商品にはオプションが用意されています。必要なオプションを選択してください。</p>
                        @if (!empty($selectedProductOptions))
                            <ul class="space-y-2">
                                @foreach ($selectedProductOptions as $option)
                                    <li>
                                        <label class="block">
                                            <input type="checkbox" name="option" wire:model="selectedOptionIds" value="{{ $option['id'] }}">
                                            {{ $option['option_name'] }} (追加料金: ¥{{ number_format($option['price']) }})
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="flex justify-between space-x-4">
                        <button type="button"
                            wire:click="cancelOptionSelection"
                            class="border border-red-500 text-red-500 rounded-lg px-4 py-2 transition-transform duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 hover:bg-red-500 hover:text-white"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            キャンセル
                        </button>
                        <button type="button"
                            wire:click="confirmOptionSelection"
                            class="border border-green-500 text-green-500 rounded-lg px-4 py-2 transition-transform duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 hover:bg-green-500 hover:text-white"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            確定する
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <footer class="mt-8 text-center text-sm text-gray-600">
            Icon by <a href="https://icons8.com">Icons8</a>
        </footer>
    </div>
</x-filament::page>
