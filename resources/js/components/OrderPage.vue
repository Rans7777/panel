<template>
  <div class="min-h-screen p-5 transition-all duration-300" :class="{ 'bg-gray-900 text-gray-100': isDarkMode, 'bg-white text-gray-800': !isDarkMode }">
    <!-- 雨のエフェクト -->
    <div v-if="isRainMode" class="rain-container fixed inset-0 pointer-events-none z-50">
      <div v-for="n in 100" :key="n" class="raindrop" :style="{ 
        left: `${Math.random() * 100}%`,
        animationDuration: `${Math.random() * 1 + 0.5}s`,
        animationDelay: `${Math.random() * 2}s`
      }"></div>
    </div>
    <div class="max-w-7xl mx-auto">
      <h1 class="text-4xl font-bold text-center mb-8" :class="{ 'text-gray-100': isDarkMode, 'text-gray-800': !isDarkMode }">注文ページ</h1>

      <!-- 通知 -->
      <div class="fixed top-5 right-5 z-50 w-96 max-w-[90vw]">
        <div v-if="error" class="flex items-start p-4 mb-4 rounded-lg shadow-lg bg-red-500/90 text-white border-l-4 border-red-600 backdrop-blur">
          <div class="w-6 h-6 mr-3 flex items-center justify-center rounded-full bg-white/20">
            <i class="pi pi-times"></i>
          </div>
          <div class="flex-1 text-sm leading-5">{{ error }}</div>
          <button @click="error = ''" class="ml-2 text-white/80 hover:text-white">
            <i class="pi pi-times"></i>
          </button>
        </div>
        <div v-if="message" class="flex items-start p-4 mb-4 rounded-lg shadow-lg bg-green-500/90 text-white border-l-4 border-green-600 backdrop-blur">
          <div class="w-6 h-6 mr-3 flex items-center justify-center rounded-full bg-white/20">
            <i class="pi pi-check"></i>
          </div>
          <div class="flex-1 text-sm leading-5">{{ message }}</div>
          <button @click="message = ''" class="ml-2 text-white/80 hover:text-white">
            <i class="pi pi-times"></i>
          </button>
        </div>
      </div>

      <!-- 商品カード一覧 -->
      <div class="conveyor-container" :class="{ 'conveyor-mode': isConveyorMode }">
        <div class="conveyor-track">
          <div
            v-for="(product, index) in products.filter(p => p.stock > 0)"
            :key="product.id"
            @click="handleProductClick(product.id)"
            class="conveyor-item cursor-pointer transition-all duration-200 hover:-translate-y-1 hover:shadow-lg rounded-lg overflow-hidden"
            :class="{ 
              'bg-gray-800 border border-gray-700': isDarkMode, 
              'bg-white border border-gray-200': !isDarkMode,
              'drop-mode': isDropMode && clickedProductId === product.id,
              'rotate-mode': isRagingMode && clickedProductId === product.id
            }"
            :style="isConveyorMode ? {
              '--item-index': index,
              '--total-items': products.filter(p => p.stock > 0).length
            } : {}"
          >
            <div class="p-4">
              <div class="flex justify-center items-center h-24 mb-4">
                <img 
                  v-if="product.image" 
                  :src="'/storage/' + product.image" 
                  :alt="product.name"
                  class="w-24 h-24 object-contain"
                />
                <div v-else class="w-12 h-12 flex items-center justify-center">
                  <i class="pi pi-image text-[5rem]" :class="{ 'text-gray-600': isDarkMode, 'text-gray-300': !isDarkMode }"></i>
                </div>
              </div>
              <h3 class="font-bold text-lg mb-2 break-words" :class="{ 'text-gray-100': isDarkMode, 'text-gray-800': !isDarkMode }">
                {{ product.name }}
              </h3>
              <p class="text-xl font-bold" :class="{ 'text-red-400': isDarkMode, 'text-red-500': !isDarkMode }">
                {{ General.formatPrice(product.price) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- カートセクション -->
      <div class="mt-8">
        <h2 class="text-2xl font-bold mb-4 pb-2 border-b" :class="{ 'border-gray-700': isDarkMode, 'border-gray-200': !isDarkMode }">
          カート
        </h2>

        <!-- デスクトップテーブル -->
        <div class="hidden md:block overflow-x-auto rounded-lg border" :class="{ 'border-gray-700 bg-gray-800': isDarkMode, 'border-gray-200 bg-white': !isDarkMode }">
          <table class="w-full">
            <thead>
              <tr :class="{ 'bg-gray-700': isDarkMode, 'bg-gray-50': !isDarkMode }">
                <th class="px-4 py-3 text-left">
                  <input
                    type="checkbox"
                    v-model="selectAll"
                    @change="toggleSelectAll"
                    class="appearance-none w-4 h-4 rounded border-2 transition-colors cursor-pointer"
                    :class="{
                      'border-gray-600 bg-gray-700 checked:bg-blue-500': isDarkMode,
                      'border-gray-300 bg-white checked:bg-blue-500': !isDarkMode
                    }"
                  />
                </th>
                <th class="px-4 py-3 text-left">商品名</th>
                <th class="px-4 py-3 text-center">単価</th>
                <th class="px-4 py-3 text-center">数量</th>
                <th class="px-4 py-3 text-center">小計</th>
                <th class="px-4 py-3 text-center">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in cart" :key="index" class="border-t" :class="{ 'border-gray-700': isDarkMode, 'border-gray-200': !isDarkMode }">
                <td class="px-4 py-3">
                  <input
                    type="checkbox"
                    v-model="selectedItems[index]"
                    class="appearance-none w-4 h-4 rounded border-2 transition-colors cursor-pointer"
                    :class="{
                      'border-gray-600 bg-gray-700 checked:bg-blue-500': isDarkMode,
                      'border-gray-300 bg-white checked:bg-blue-500': !isDarkMode
                    }"
                  />
                </td>
                <td class="px-4 py-3">
                  {{ item.name }}
                  <div v-if="item.options && item.options.length > 0" class="mt-1 text-sm" :class="{ 'text-gray-400': isDarkMode, 'text-gray-500': !isDarkMode }">
                    オプション:<br>
                    <span v-for="option in item.options" :key="option.id" class="ml-2 block italic">
                      {{ option.option_name }} (追加料金: {{ General.formatPrice(option.price) }})
                    </span>
                  </div>
                </td>
                <td class="px-4 py-3 text-center">{{ General.formatPrice(item.price) }}</td>
                <td class="px-4 py-3 text-center">
                  <input 
                    type="number"
                    v-model.number="item.quantity"
                    min="1"
                    class="w-20 px-2 py-1 text-center rounded border"
                    :class="{ 'bg-gray-700 border-gray-600': isDarkMode, 'bg-white border-gray-300': !isDarkMode }"
                    @change="updateQuantity(index, item.quantity)"
                  />
                </td>
                <td class="px-4 py-3 text-center">{{ General.formatPrice(item.price * item.quantity) }}</td>
                <td class="px-4 py-3 text-center">
                  <button 
                    @click="handleDeleteClick(index)"
                    class="px-4 py-2 rounded text-white bg-red-500 hover:bg-red-600 transition-colors"
                  >
                    削除
                  </button>
                </td>
              </tr>
              <tr v-if="cart.length === 0">
                <td colspan="5" class="px-4 py-8 text-center" :class="{ 'text-gray-400': isDarkMode, 'text-gray-500': !isDarkMode }">
                  カートに商品がありません
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- モバイルカート -->
        <div class="md:hidden space-y-4">
          <div v-if="cart.length === 0" class="text-center py-8" :class="{ 'text-gray-400': isDarkMode, 'text-gray-500': !isDarkMode }">
            カートに商品がありません
          </div>
          <div 
            v-for="(item, index) in cart" 
            :key="index"
            class="rounded-lg p-4"
            :class="{ 'bg-gray-800 border border-gray-700': isDarkMode, 'bg-white border border-gray-200': !isDarkMode }"
          >
            <div class="flex justify-between items-start mb-2">
              <h3 class="font-bold">{{ item.name }}</h3>
              <button 
                @click="handleDeleteClick(index)"
                class="w-8 h-8 flex items-center justify-center rounded-full text-white bg-red-500"
              >
                <i class="pi pi-times"></i>
              </button>
            </div>
            <div 
              v-if="item.options && item.options.length > 0" 
              class="mb-2 p-2 rounded text-sm"
              :class="{ 'bg-gray-700/50': isDarkMode, 'bg-gray-100': !isDarkMode }"
            >
              <div class="font-bold mb-1">オプション:</div>
              <div 
                v-for="option in item.options" 
                :key="option.id"
                class="pl-2"
                :class="{ 'text-gray-400': isDarkMode, 'text-gray-600': !isDarkMode }"
              >
                {{ option.option_name }} ({{ General.formatPrice(option.price) }})
              </div>
            </div>
            <div class="flex justify-between items-center mt-4">
              <div class="font-bold" :class="{ 'text-red-400': isDarkMode, 'text-red-500': !isDarkMode }">
                {{ General.formatPrice(item.price) }}
              </div>
              <div class="flex items-center gap-2">
                <button 
                  class="w-8 h-8 rounded flex items-center justify-center border"
                  :class="{ 'bg-gray-700 border-gray-600': isDarkMode, 'bg-gray-100 border-gray-300': !isDarkMode }"
                  @click="updateQuantity(index, Math.max(1, item.quantity - 1))"
                >−</button>
                <span class="font-bold w-8 text-center">{{ item.quantity }}</span>
                <button 
                  class="w-8 h-8 rounded flex items-center justify-center border"
                  :class="{ 'bg-gray-700 border-gray-600': isDarkMode, 'bg-gray-100 border-gray-300': !isDarkMode }"
                  @click="updateQuantity(index, item.quantity + 1)"
                >+</button>
              </div>
            </div>
          </div>
        </div>

        <!-- 合計金額と注文ボタン -->
        <div class="mt-8">
          <div class="flex justify-between items-center mb-4">
            <button 
              v-if="hasSelectedItems"
              @click="handleBulkDeleteClick"
              class="px-4 py-2 rounded text-white bg-red-500 hover:bg-red-600 transition-colors"
            >
              選択した商品を削除
            </button>
            <div class="text-xl font-bold">
              合計金額: {{ General.formatPrice(totalPrice) }}
            </div>
          </div>
          <div class="flex justify-end">
            <button 
              @click="showPaymentModal"
              class="px-6 py-3 rounded text-white text-lg transition-colors"
              :class="{ 'bg-green-600 hover:bg-green-700': !isDarkMode, 'bg-green-700 hover:bg-green-800': isDarkMode }"
            >
              会計へ進む
            </button>
          </div>
        </div>
      </div>

      <!-- オプション選択ポップアップ -->
      <div v-if="showOptionsPopup" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div 
          class="w-full max-w-md rounded-lg p-6"
          :class="{ 'bg-gray-800': isDarkMode, 'bg-white': !isDarkMode }"
        >
          <h3 class="text-xl font-bold mb-4">オプション選択</h3>
          <p class="mb-4">この商品にはオプションが用意されています。必要に応じて選択してください。</p>
          
          <div v-if="productOptions.length > 0" class="space-y-4 mb-6">
            <div 
              v-for="option in productOptions" 
              :key="option.id"
              class="flex items-center gap-3"
            >
              <input
                type="checkbox"
                :id="`option-${option.id}`"
                :value="option.id"
                v-model="selectedOptionIds"
                class="w-4 h-4"
              />
              <label :for="`option-${option.id}`">
                {{ option.option_name }} (追加料金: {{ General.formatPrice(option.price) }})
              </label>
            </div>
          </div>
          <div v-else class="mb-6">
            <p>この商品にはオプションがありません</p>
          </div>

          <div class="flex justify-between gap-4">
            <button 
              @click="cancelOptionSelection"
              class="px-4 py-2 rounded border flex-1 transition-colors"
              :class="{
                'border-gray-600 bg-gray-700 hover:bg-gray-600': isDarkMode,
                'border-gray-300 bg-gray-100 hover:bg-gray-200': !isDarkMode
              }"
            >
              キャンセル
            </button>
            <button 
              @click="confirmOptionSelection"
              class="px-4 py-2 rounded flex-1 text-white transition-colors"
              :class="{ 'bg-green-700 hover:bg-green-800': isDarkMode, 'bg-green-600 hover:bg-green-700': !isDarkMode }"
            >
              確定する
            </button>
          </div>
        </div>
      </div>

      <!-- 支払いポップアップ -->
      <div v-if="showPaymentPopup" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div 
          class="w-full max-w-md rounded-lg p-6"
          :class="{ 'bg-gray-800': isDarkMode, 'bg-white': !isDarkMode }"
        >
          <h3 class="text-xl font-bold mb-4">お支払い</h3>
          <div class="space-y-4 mb-6">
            <p>合計金額: {{ General.formatPrice(totalPrice) }}</p>
            <div class="space-y-2">
              <label for="payment-amount">お支払い金額:</label>
              <input 
                type="number" 
                id="payment-amount" 
                v-model.number="paymentAmount" 
                @input="validatePaymentInput"
                min="0"
                pattern="[0-9]*"
                inputmode="numeric"
                class="w-full px-3 py-2 rounded border"
                :class="{
                  'bg-gray-700 border-gray-600': isDarkMode,
                  'bg-white border-gray-300': !isDarkMode
                }"
              />
            </div>
            <p>おつり: {{ General.formatPrice(changeAmount) }}</p>
          </div>
          <div class="flex justify-between gap-4">
            <button 
              @click="showPaymentPopup = false"
              class="px-4 py-2 rounded border flex-1 transition-colors"
              :class="{
                'border-gray-600 bg-gray-700 hover:bg-gray-600': isDarkMode,
                'border-gray-300 bg-gray-100 hover:bg-gray-200': !isDarkMode
              }"
            >
              キャンセル
            </button>
            <button 
              @click="confirmOrder"
              :disabled="paymentAmount < totalPrice"
              class="px-4 py-2 rounded flex-1 text-white transition-colors disabled:opacity-50"
              :class="{ 'bg-green-700 hover:bg-green-800': isDarkMode, 'bg-green-600 hover:bg-green-700': !isDarkMode }"
            >
              注文確定
            </button>
          </div>
        </div>
      </div>

      <div v-if="showDeleteConfirmation" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div 
          class="w-full max-w-md rounded-lg p-6"
          :class="{ 'bg-gray-800': isDarkMode, 'bg-white': !isDarkMode }"
        >
          <h3 class="text-xl font-bold mb-4">商品の削除</h3>
          <p class="mb-6">この商品をカートから削除してもよろしいですか？</p>
          <div class="flex justify-end gap-4">
            <button 
              @click="cancelDelete"
              class="px-4 py-2 rounded border transition-colors"
              :class="{
                'border-gray-600 bg-gray-700 hover:bg-gray-600': isDarkMode,
                'border-gray-300 bg-gray-100 hover:bg-gray-200': !isDarkMode
              }"
            >
              キャンセル
            </button>
            <button 
              @click="confirmDelete"
              class="px-4 py-2 rounded text-white bg-red-500 hover:bg-red-600 transition-colors"
            >
              削除する
            </button>
          </div>
        </div>
      </div>

      <!-- 一括削除確認ポップアップ -->
      <div v-if="showBulkDeleteConfirmation" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div 
          class="w-full max-w-md rounded-lg p-6"
          :class="{ 'bg-gray-800': isDarkMode, 'bg-white': !isDarkMode }"
        >
          <h3 class="text-xl font-bold mb-4">商品の一括削除</h3>
          <p class="mb-6">選択した商品をカートから削除してもよろしいですか？</p>
          <div class="flex justify-end gap-4">
            <button 
              @click="cancelBulkDelete"
              class="px-4 py-2 rounded border transition-colors"
              :class="{
                'border-gray-600 bg-gray-700 hover:bg-gray-600': isDarkMode,
                'border-gray-300 bg-gray-100 hover:bg-gray-200': !isDarkMode
              }"
            >
              キャンセル
            </button>
            <button 
              @click="confirmBulkDelete"
              class="px-4 py-2 rounded text-white bg-red-500 hover:bg-red-600 transition-colors"
            >
              削除する
            </button>
          </div>
        </div>
      </div>

      <!-- テーマ切り替えボタン -->
      <button 
        v-if="cart.length === 0"
        @click="toggleDarkMode"
        class="fixed bottom-5 right-5 px-4 py-2 rounded-full shadow-lg transition-colors text-white"
        :class="{ 'bg-gray-700 hover:bg-gray-600': isDarkMode, 'bg-green-600 hover:bg-green-700': !isDarkMode }"
      >
        <i :class="isDarkMode ? 'pi pi-sun' : 'pi pi-moon'" class="mr-2"></i>
        {{ isDarkMode ? 'ライトモード' : 'ダークモード' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';
import { v4 as uuidv4 } from 'uuid';
import General from '../utils/General';

const products = ref([]);
const productOptions = ref([]);
const cart = ref([]);
const totalPrice = ref(0);
const showOptionsPopup = ref(false);
const showPaymentPopup = ref(false);
const selectedProductId = ref(null);
const selectedOptionIds = ref([]);
const paymentAmount = ref(0);
const changeAmount = ref(0);
const message = ref('');
const error = ref('');
const isDarkMode = ref(false);
const showDeleteConfirmation = ref(false);
const deleteTargetIndex = ref(null);
const messageTimer = ref(null);
const errorTimer = ref(null);
const clickedProductId = ref(null);
const dropTimer = ref(null);
const hiddenModeType = ref('');
const isRagingMode = ref(false);
const isDropMode = ref(false);
const isRainMode = ref(false);
const isConveyorMode = ref(false);
const selectedItems = ref([]);
const selectAll = ref(false);
const showBulkDeleteConfirmation = ref(false);

// メッセージ通知を表示する関数
const showMessage = (msg) => {
  if (messageTimer.value) {
    clearTimeout(messageTimer.value);
  }
  message.value = msg;
  messageTimer.value = setTimeout(() => {
    message.value = '';
  }, 3000);
};

// エラー通知を表示する関数
const showError = (err) => {
  if (errorTimer.value) {
    clearTimeout(errorTimer.value);
  }
  error.value = err;
  errorTimer.value = setTimeout(() => {
    error.value = '';
  }, 3000);
};

// システムのダークモード設定を検出
const detectDarkMode = () => {
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme) {
    isDarkMode.value = savedTheme === 'dark';
  } else {
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      isDarkMode.value = true;
    }
    if (document.documentElement.classList.contains('dark')) {
      isDarkMode.value = true;
    }
  }
  applyDarkMode();
};

// ダークモードの切り替え
const toggleDarkMode = () => {
  isDarkMode.value = !isDarkMode.value;
  localStorage.setItem('theme', isDarkMode.value ? 'dark' : 'light');
  applyDarkMode();
};

// ダークモード設定を適用
const applyDarkMode = () => {
  if (isDarkMode.value) {
    document.documentElement.classList.add('dark-mode');
    document.body.classList.add('dark-mode');
    document.documentElement.style.backgroundColor = '#121827';
    document.body.style.backgroundColor = '#121827';
    document.documentElement.style.color = '#f3f4f6';
    document.body.style.color = '#f3f4f6';
    document.documentElement.style.height = '100%';
    document.body.style.height = '100%';
    document.documentElement.style.margin = '0';
    document.body.style.margin = '0';
  } else {
    document.documentElement.classList.remove('dark-mode');
    document.body.classList.remove('dark-mode');
    document.documentElement.style.backgroundColor = '#fff';
    document.body.style.backgroundColor = '#fff';
    document.documentElement.style.color = '#1f2937';
    document.body.style.color = '#1f2937';
    document.documentElement.style.height = '100%';
    document.body.style.height = '100%';
    document.documentElement.style.margin = '0';
    document.body.style.margin = '0';
  }
};

// システムのダークモード設定変更を監視
const watchSystemTheme = () => {
  if (window.matchMedia) {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    mediaQuery.addEventListener('change', (e) => {
      if (!localStorage.getItem('theme')) {
        isDarkMode.value = e.matches;
        applyDarkMode();
      }
    });
  }
};

// 隠しモードをチェック
const checkHiddenMode = () => {
  const urlParams = new URLSearchParams(window.location.search);
  const hiddenParam = urlParams.get('hidden');
  // 隠しモードの設定: URLパラメータ `hidden` の値によって異なるモードを有効化
  // - 'drop': ドロップモード（要素がアニメーションで落下）
  // - 'rotate': 回転モード（要素がアニメーションで回転）
  // - 'rain': 雨モード（画面上に雨のアニメーションを表示）
  // - 'conveyor': コンベアモード（商品をコンベアベルトのように表示）
  isDropMode.value = hiddenParam === 'drop';
  isRagingMode.value = hiddenParam === 'rotate';
  isRainMode.value = hiddenParam === 'rain';
  isConveyorMode.value = hiddenParam === 'conveyor';
  hiddenModeType.value = hiddenParam || '';
};

// API から製品情報を取得
const loadProducts = async () => {
  try {
    const response = await axios.get('/api/products');
    products.value = response.data.data;
    loadCartFromSession();
  } catch (err) {
    showError('商品情報の取得に失敗しました');
  }
};

// 商品クリック時の処理
const handleProductClick = async (productId) => {
  try {
    const product = products.value.find(p => p.id === productId);

    if (!product) {
      showError('商品情報が見つかりません');
      return;
    }

    // 隠しモードの処理
    if (isDropMode.value || isRagingMode.value) {
      clickedProductId.value = productId;
      if (dropTimer.value) {
        clearTimeout(dropTimer.value);
      }
      dropTimer.value = setTimeout(() => {
        clickedProductId.value = null;
      }, 1000);
    }

    if (product.options && product.options.length > 0) {
      selectedProductId.value = productId;
      productOptions.value = product.options;
      showOptionsPopup.value = true;
    } else {
      addToCart(productId);
    }
  } catch (err) {
    showError('商品の処理に失敗しました');
  }
};

// カートの内容をセッションストレージに保存
const saveCartToSession = () => {
  sessionStorage.setItem('cart', JSON.stringify(cart.value));
};

// セッションストレージからカートの内容を復元
const loadCartFromSession = () => {
  const savedCart = sessionStorage.getItem('cart');
  if (savedCart) {
    const parsedCart = JSON.parse(savedCart);
    const removedItems = [];
    const validItems = [];

    for (const item of parsedCart) {
      const product = products.value.find(p => p.id === item.id);
      if (!product || product.stock <= 0) {
        removedItems.push(item);
      } else {
        if (item.quantity > product.stock) {
          item.quantity = product.stock;
        }
        validItems.push({
          id: item.id,
          name: item.name,
          image: item.image,
          price: item.price,
          quantity: item.quantity,
          options: item.options ? JSON.parse(JSON.stringify(item.options)) : null
        });
      }
    }

    cart.value = validItems;

    selectedItems.value = new Array(cart.value.length).fill(false);

    if (removedItems.length > 0) {
      const itemNames = removedItems.map(item => item.name).join(',');
      showError(`${itemNames}は在庫切れのため、カートから削除されました`);
    }

    if (cart.value.length > 0) {
      calculateTotalPrice();
      saveCartToSession();
    } else {
      sessionStorage.removeItem('cart');
    }
  }
};

// カートに商品を追加
const addToCart = (productId) => {
  // 商品IDから商品を検索
  const product = products.value.find(p => p.id === productId);

  if (!product) {
    showError('商品情報が見つかりません');
    return;
  }

  // 在庫チェック
  if (product.stock <= 0) {
    showError('在庫がありません: ' + product.name);
    return;
  }

  // 同一商品（オプションがない場合）の場合は数量をインクリメント
  const existingItemIndex = cart.value.findIndex(item => 
    item.id === product.id && !item.options
  );

  if (existingItemIndex !== -1) {
    const currentQuantity = cart.value[existingItemIndex].quantity;
    if (currentQuantity < product.stock) {
      cart.value[existingItemIndex].quantity = currentQuantity + 1;
    } else {
      showError('在庫数を超えています: ' + product.name);
    }
  } else {
    cart.value.push({
      id: product.id,
      name: product.name,
      image: product.image,
      price: product.price,
      quantity: 1,
      options: null
    });
  }

  calculateTotalPrice();
  saveCartToSession();
  showMessage('商品がカートに追加されました');
  if (selectedItems.value.length < cart.value.length) {
    selectedItems.value.push(false);
  }
};

// カート内の商品数量を更新
const updateQuantity = (index, quantity) => {
  if (!cart.value[index]) {
    showError('カートに該当する商品が存在しません');
    return;
  }

  if (quantity <= 0) {
    removeFromCart(index);
    return;
  }

  const product = products.value.find(p => p.id === cart.value[index].id);

  if (!product) {
    showError('商品が存在しません');
    removeFromCart(index);
    return;
  }

  if (quantity > product.stock) {
    showError('在庫数を超えています: ' + product.name);
    cart.value[index].quantity = product.stock;
  } else {
    cart.value[index].quantity = quantity;
  }

  calculateTotalPrice();
  saveCartToSession();
};

// カートから商品を削除
const removeFromCart = (index) => {
  if (!cart.value[index]) {
    showError('カートに該当する商品が存在しません');
    return;
  }

  cart.value.splice(index, 1);
  calculateTotalPrice();
  saveCartToSession();
  selectedItems.value.splice(index, 1);
};

// カート内の商品の合計金額を計算
const calculateTotalPrice = () => {
  totalPrice.value = parseInt(cart.value.reduce((sum, item) => {
    return sum + (item.price * item.quantity);
  }, 0));
};

// オプション選択を確定
const confirmOptionSelection = () => {
  if (!selectedProductId.value) {
    showError('商品が選択されていません');
    return;
  }

  const product = products.value.find(p => p.id === selectedProductId.value);

  if (!product) {
    showError('商品情報が見つかりません');
    resetOptionSelection();
    return;
  }

  if (selectedOptionIds.value.length === 0) {
    addToCart(selectedProductId.value);
    resetOptionSelection();
    return;
  }

  const selectedOptions = productOptions.value.filter(option => 
    selectedOptionIds.value.includes(option.id)
  );

  if (selectedOptions.length === 0) {
    showError('選択されたオプションが存在しません');
    return;
  }

  // オプションの追加料金を計算
  const additionalPrice = parseInt(selectedOptions.reduce((sum, option) => sum + Number(option.price), 0));
  const totalItemPrice = parseInt(Number(product.price) + additionalPrice);

  // 同じ商品とオプションの組み合わせがカートにあるかチェック
  const existingItemIndex = cart.value.findIndex(item => {
    if (item.id === product.id && item.options) {
      const existingOptionIds = item.options.map(opt => opt.id).sort();
      const currentOptionIds = [...selectedOptionIds.value].sort();
      return JSON.stringify(existingOptionIds) === JSON.stringify(currentOptionIds);
    }
    return false;
  });

  if (existingItemIndex !== -1) {
    if (cart.value[existingItemIndex].quantity < product.stock) {
      cart.value[existingItemIndex].quantity++;
    } else {
      showError('在庫数を超えています: ' + product.name);
    }
  } else {
    cart.value.push({
      id: product.id,
      name: product.name,
      image: product.image,
      price: totalItemPrice,
      quantity: 1,
      options: JSON.parse(JSON.stringify(selectedOptions))
    });
  }

  calculateTotalPrice();
  resetOptionSelection();
  saveCartToSession();
  showMessage('商品とオプションがカートに追加されました');
};

const cancelOptionSelection = () => {
  resetOptionSelection();
};

const resetOptionSelection = () => {
  selectedProductId.value = null;
  selectedOptionIds.value = [];
  showOptionsPopup.value = false;
};

// 支払いモーダルを表示
const showPaymentModal = () => {
  if (cart.value.length === 0) {
    showError('カートが空です');
    return;
  }

  // オプションがない場合は支払いポップアップを表示
  paymentAmount.value = 0;
  changeAmount.value = 0;
  showPaymentPopup.value = true;
};

// おつりを計算
const calculateChange = () => {
  const change = parseInt(paymentAmount.value - totalPrice.value);
  changeAmount.value = change < 0 ? 0 : change;
};

// お支払い金額の入力を検証
const validatePaymentInput = (event) => {
  // 入力値が数値でない場合は空にする
  if (isNaN(event.target.value) || event.target.value === '') {
    paymentAmount.value = 0;
  } else {
    // 数値の場合は整数に変換
    paymentAmount.value = parseInt(event.target.value, 10);
  }

  // おつりを計算
  calculateChange();
};

// 注文を確定
const confirmOrder = async () => {
  if (paymentAmount.value < totalPrice.value) {
    showError('支払い金額が不足しています');
    return;
  }

  try {
    const orderUuid = uuidv4();

    // カートデータを整形
    const cartData = cart.value.map(item => ({
      id: item.id,
      uuid: orderUuid,
      name: item.name,
      image: item.image,
      price: item.price,
      quantity: item.quantity,
      options: item.options || null
    }));

    const orderData = {
      cart: cartData,
      paymentAmount: paymentAmount.value,
      changeAmount: changeAmount.value
    };

    const response = await axios.post('/api/orders', orderData);

    if (response.status === 201) {
      cart.value = [];
      totalPrice.value = 0;
      sessionStorage.removeItem('cart');
      showPaymentPopup.value = false;
      showMessage('注文が確定しました！');
      await loadProducts();
    } else {
      showError('注文の確定に失敗しました');
      showPaymentPopup.value = false;
    }
  } catch (err) {
    showError(err.response?.data?.message || '内部処理に失敗しました');
    showPaymentPopup.value = false;
  }
};

const handleDeleteClick = (index) => {
  deleteTargetIndex.value = index;
  showDeleteConfirmation.value = true;
};

const confirmDelete = () => {
  if (deleteTargetIndex.value !== null) {
    removeFromCart(deleteTargetIndex.value);
    showDeleteConfirmation.value = false;
    deleteTargetIndex.value = null;
    showMessage('商品をカートから削除しました');
  }
};

const cancelDelete = () => {
  showDeleteConfirmation.value = false;
  deleteTargetIndex.value = null;
};

const toggleSelectAll = () => {
  selectedItems.value = new Array(cart.value.length).fill(selectAll.value);
};

const hasSelectedItems = computed(() => {
  return selectedItems.value.some(selected => selected);
});

const handleBulkDeleteClick = () => {
  showBulkDeleteConfirmation.value = true;
};

const cancelBulkDelete = () => {
  showBulkDeleteConfirmation.value = false;
};

const confirmBulkDelete = () => {
  // 選択されたアイテムのインデックスを取得（降順でソート）
  // 降順でソートするのは、削除時にインデックスがずれるのを防ぐため
  const indicesToDelete = selectedItems.value
    .map((selected, index) => selected ? index : -1)
    .filter(index => index !== -1)
    .reverse();

  // 削除されるアイテムの数をカウント
  const deletedCount = indicesToDelete.length;

  indicesToDelete.forEach(index => {
    cart.value.splice(index, 1);
  });

  // カートの更新後に計算と保存を行う
  calculateTotalPrice();
  saveCartToSession();

  selectedItems.value = new Array(cart.value.length).fill(false);
  selectAll.value = false;
  showBulkDeleteConfirmation.value = false;
  showMessage(`選択した${deletedCount}個の商品を削除しました`);
};

onMounted(() => {
  loadProducts();
  detectDarkMode();
  watchSystemTheme();
  applyDarkMode();
  checkHiddenMode();
});

onUnmounted(() => {
  if (dropTimer.value) {
    clearTimeout(dropTimer.value);
  }
});
</script>

<style scoped>
@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.notification {
  animation: slideIn 0.3s ease-out forwards;
}

html, body {
  margin: 0;
  padding: 0;
  height: 100%;
  width: 100%;
}

.dark-mode {
  background-color: #121827;
  color: #f3f4f6;
}

@keyframes drop {
  0% {
    transform: translateY(0) rotate(0deg);
    opacity: 1;
  }
  50% {
    transform: translateY(50vh) rotate(180deg);
    opacity: 0.8;
  }
  100% {
    transform: translateY(100vh) rotate(360deg);
    opacity: 0;
  }
}

.drop-mode {
  animation: drop 1s ease-in forwards;
  pointer-events: none;
  z-index: 10;
  position: relative;
}

@keyframes rotate {
  0% {
    transform: rotate(0deg);
    opacity: 1;
  }
  25% {
    transform: rotate(900deg);
  }
  50% {
    transform: rotate(1800deg);
  }
  75% {
    transform: rotate(2700deg);
  }
  100% {
    transform: rotate(3600deg);
    opacity: 0;
  }
}

.rotate-mode {
  animation: rotate 2s ease-in-out forwards;
  pointer-events: none;
  z-index: 10;
  position: relative;
}

:root {
  overflow-x: hidden;
}

body {
  overflow-x: hidden;
}

@keyframes rain {
  0% {
    transform: translateY(-100vh);
    opacity: 0;
  }
  10% {
    opacity: 1;
  }
  90% {
    opacity: 1;
  }
  100% {
    transform: translateY(100vh);
    opacity: 0;
  }
}

.rain-container {
  overflow: hidden;
}

.raindrop {
  position: absolute;
  width: 2px;
  height: 100px;
  background: linear-gradient(transparent, #4a90e2);
  animation: rain linear infinite;
  will-change: transform, opacity;
}

.perspective-container {
  perspective: 1000px;
  transform-style: preserve-3d;
}

@keyframes conveyor {
  0% {
    transform: translateZ(0) rotateY(0deg) translateX(0);
  }
  25% {
    transform: translateZ(100px) rotateY(90deg) translateX(100px);
  }
  50% {
    transform: translateZ(200px) rotateY(180deg) translateX(0);
  }
  75% {
    transform: translateZ(100px) rotateY(270deg) translateX(-100px);
  }
  100% {
    transform: translateZ(0) rotateY(360deg) translateX(0);
  }
}

.conveyor-mode {
  animation: conveyor var(--conveyor-duration) linear infinite;
  animation-delay: var(--conveyor-delay);
  transform-style: preserve-3d;
  backface-visibility: visible;
}

.conveyor-mode:hover {
  animation-play-state: paused;
}

.conveyor-container {
  position: relative;
  width: 100%;
  height: 300px;
  overflow: hidden;
  margin-bottom: 2rem;
}

.conveyor-track {
  position: absolute;
  display: flex;
  gap: 1rem;
  padding: 1rem;
  width: fit-content;
  height: 100%;
}

.conveyor-item {
  flex: 0 0 auto;
  width: 250px;
  position: relative;
}

.conveyor-mode .conveyor-item {
  transform: translateX(100vw);
  opacity: 0;
  animation: moveItem calc(10s + (var(--total-items) * 0.5s)) linear infinite;
  animation-delay: calc(var(--item-index) * (15s / var(--total-items)));
  opacity: 1;
}

.conveyor-item {
  transform: none;
  opacity: 1;
  display: block;
}

@keyframes moveItem {
  0% {
    transform: translateX(100vw);
    opacity: 1;
  }
  85% {
    transform: translateX(-100vw);
    opacity: 1;
  }
  86% {
    transform: translateX(-100vw);
    opacity: 0;
  }
  87% {
    transform: translateX(100vw);
    opacity: 0;
  }
  88% {
    transform: translateX(100vw);
    opacity: 1;
  }
  100% {
    transform: translateX(100vw);
    opacity: 1;
  }
}

.conveyor-mode .conveyor-track {
  animation: none;
}
</style>
