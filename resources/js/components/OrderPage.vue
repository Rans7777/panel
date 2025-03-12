<template>
  <div class="min-h-screen p-5 transition-all duration-300" :class="{ 'bg-gray-900 text-gray-100': isDarkMode, 'bg-white text-gray-800': !isDarkMode }">
    <div class="max-w-7xl mx-auto">
      <h1 class="text-4xl font-bold text-center mb-8" :class="{ 'text-gray-100': isDarkMode, 'text-gray-800': !isDarkMode }">注文ページ</h1>

      <!-- 通知 -->
      <div class="fixed top-5 right-5 z-50 w-96 max-w-[90vw]">
        <div v-if="error" class="flex items-start p-4 mb-4 rounded-lg shadow-lg bg-red-500/90 text-white border-l-4 border-red-600 backdrop-blur">
          <div class="w-6 h-6 mr-3 flex items-center justify-center rounded-full bg-white/20">✕</div>
          <div class="flex-1 text-sm leading-5">{{ error }}</div>
        </div>
        <div v-if="message" class="flex items-start p-4 mb-4 rounded-lg shadow-lg bg-green-500/90 text-white border-l-4 border-green-600 backdrop-blur">
          <div class="w-6 h-6 mr-3 flex items-center justify-center rounded-full bg-white/20">✓</div>
          <div class="flex-1 text-sm leading-5">{{ message }}</div>
        </div>
      </div>

      <!-- 商品カード一覧 -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5 mb-8">
        <div
          v-for="product in products"
          :key="product.id"
          @click="handleProductClick(product.id)"
          class="cursor-pointer transition-all duration-200 hover:-translate-y-1 hover:shadow-lg rounded-lg overflow-hidden"
          :class="{ 'bg-gray-800 border border-gray-700': isDarkMode, 'bg-white border border-gray-200': !isDarkMode }"
        >
          <div class="p-4">
            <div class="flex justify-center items-center h-24 mb-4">
              <img 
                v-if="product.image" 
                :src="'/storage/' + product.image" 
                :alt="product.name"
                class="w-24 h-24 object-contain"
              />
              <div v-else class="w-12 h-12">
                <img width="48" height="48" src="https://img.icons8.com/badges/48/shopping-basket.png" alt="shopping-basket"/>
              </div>
            </div>
            <h3 class="font-bold text-lg mb-2 break-words" :class="{ 'text-gray-100': isDarkMode, 'text-gray-800': !isDarkMode }">
              {{ product.name }}
            </h3>
            <p class="text-xl font-bold" :class="{ 'text-red-400': isDarkMode, 'text-red-500': !isDarkMode }">
              ¥{{ product.price }}
            </p>
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
                  {{ item.name }}
                  <div v-if="item.options && item.options.length > 0" class="mt-1 text-sm" :class="{ 'text-gray-400': isDarkMode, 'text-gray-500': !isDarkMode }">
                    オプション:<br>
                    <span v-for="option in item.options" :key="option.id" class="ml-2 block italic">
                      {{ option.option_name }} (追加料金: ¥{{ option.price }})
                    </span>
                  </div>
                </td>
                <td class="px-4 py-3 text-center">¥{{ item.price }}</td>
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
                <td class="px-4 py-3 text-center">¥{{ item.price * item.quantity }}</td>
                <td class="px-4 py-3 text-center">
                  <button 
                    @click="removeFromCart(index)"
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
                @click="removeFromCart(index)"
                class="w-8 h-8 flex items-center justify-center rounded-full text-white bg-red-500"
              >
                ×
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
                {{ option.option_name }} (¥{{ option.price }})
              </div>
            </div>
            <div class="flex justify-between items-center mt-4">
              <div class="font-bold" :class="{ 'text-red-400': isDarkMode, 'text-red-500': !isDarkMode }">
                ¥{{ item.price }}
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
          <div class="text-right text-xl font-bold mb-4">
            合計金額: ¥{{ totalPrice }}
          </div>
          <div class="flex justify-end">
            <button 
              @click="showPaymentModal"
              class="px-6 py-3 rounded text-white text-lg transition-colors"
              :class="{ 'bg-green-600 hover:bg-green-700': !isDarkMode, 'bg-green-700 hover:bg-green-800': isDarkMode }"
            >
              注文を確定する
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
                {{ option.option_name }} (追加料金: ¥{{ option.price }})
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
            <p>合計金額: ¥{{ totalPrice }}</p>
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
            <p>おつり: ¥{{ changeAmount }}</p>
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

      <!-- テーマ切り替えボタン -->
      <button 
        @click="toggleDarkMode"
        class="fixed bottom-5 right-5 px-4 py-2 rounded-full shadow-lg transition-colors text-white"
        :class="{ 'bg-gray-700 hover:bg-gray-600': isDarkMode, 'bg-green-600 hover:bg-green-700': !isDarkMode }"
      >
        {{ isDarkMode ? '🌞 ライトモード' : '🌙 ダークモード' }}
      </button>

      <!-- アトリビューション -->
      <div class="text-center text-sm mt-8" :class="{ 'text-gray-400': isDarkMode, 'text-gray-500': !isDarkMode }">
        <p>Icon by <a href="https://icons8.com" class="underline">Icons8</a></p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';

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
    document.documentElement.style.backgroundColor = '#1a1a1a';
    document.body.style.backgroundColor = '#1a1a1a';
  } else {
    document.documentElement.classList.remove('dark-mode');
    document.body.classList.remove('dark-mode');
    document.documentElement.style.backgroundColor = '#fff';
    document.body.style.backgroundColor = '#fff';
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

// API から製品情報を取得
const loadProducts = async () => {
  try {
    const response = await axios.get('/api/products');
    products.value = response.data;
  } catch (err) {
    error.value = '製品情報の取得に失敗しました';
  }
};

// 商品クリック時の処理
const handleProductClick = async (productId) => {
  try {
    // 商品IDから商品を検索
    const product = products.value.find(p => p.id === productId);

    if (!product) {
      error.value = '商品情報が見つかりません';
      return;
    }

    // 商品データから直接オプション情報を取得
    if (product.options && product.options.length > 0) {
      selectedProductId.value = productId;
      productOptions.value = product.options;
      showOptionsPopup.value = true;
    } else {
      // オプションがない場合は直接カートに追加
      addToCart(productId);
    }
  } catch (err) {
    console.error('商品クリック処理エラー:', err);
    error.value = '商品の処理に失敗しました';
  }
};

// カートに商品を追加
const addToCart = (productId) => {
  // 商品IDから商品を検索
  const product = products.value.find(p => p.id === productId);

  if (!product) {
    error.value = '商品情報が見つかりません';
    return;
  }

  // 在庫チェック
  if (product.stock <= 0) {
    error.value = '在庫がありません: ' + product.name;
    return;
  }

  // 同一商品（オプションがない場合）の場合は数量をインクリメント
  for (let i = 0; i < cart.value.length; i++) {
    if (cart.value[i].id === product.id && !cart.value[i].options) {
      if (cart.value[i].quantity < product.stock) {
        cart.value[i].quantity++;
      } else {
        error.value = '在庫数を超えています: ' + product.name;
      }
      calculateTotalPrice();
      message.value = '商品がカートに追加されました';
      setTimeout(() => {
        message.value = '';
      }, 3000);
      return;
    }
  }

  cart.value.push({
    id: product.id,
    name: product.name,
    image: product.image,
    price: product.price,
    quantity: 1
  });

  calculateTotalPrice();
  message.value = '商品がカートに追加されました';
  setTimeout(() => {
    message.value = '';
  }, 3000);
};

// カート内の商品数量を更新
const updateQuantity = (index, quantity) => {
  if (!cart.value[index]) {
    error.value = 'カートに該当する商品が存在しません';
    return;
  }

  if (quantity <= 0) {
    removeFromCart(index);
    return;
  }

  const product = products.value.find(p => p.id === cart.value[index].id);

  if (!product) {
    error.value = '商品が存在しません';
    removeFromCart(index);
    return;
  }

  if (quantity > product.stock) {
    error.value = '在庫数を超えています: ' + product.name;
    cart.value[index].quantity = product.stock;
  } else {
    cart.value[index].quantity = quantity;
  }

  calculateTotalPrice();
};

// カートから商品を削除
const removeFromCart = (index) => {
  if (!cart.value[index]) {
    error.value = 'カートに該当する商品が存在しません';
    return;
  }

  cart.value.splice(index, 1);
  calculateTotalPrice();
};

// カート内の商品の合計金額を計算
const calculateTotalPrice = () => {
  totalPrice.value = cart.value.reduce((sum, item) => {
    return sum + (item.price * item.quantity);
  }, 0);
};

// オプション選択を確定
const confirmOptionSelection = () => {
  if (!selectedProductId.value) {
    error.value = '商品が選択されていません';
    return;
  }

  const product = products.value.find(p => p.id === selectedProductId.value);

  if (!product) {
    error.value = '商品情報が見つかりません';
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
    error.value = '選択されたオプションが存在しません';
    return;
  }

  // オプションの追加料金を計算
  const additionalPrice = selectedOptions.reduce((sum, option) => sum + option.price, 0);
  const totalItemPrice = product.price + additionalPrice;

  // 同じ商品とオプションの組み合わせがカートにあるかチェック
  for (let i = 0; i < cart.value.length; i++) {
    const item = cart.value[i];

    if (item.id === product.id && item.options) {
      const existingOptionIds = item.options.map(opt => opt.id);
      const currentOptionIds = [...selectedOptionIds.value].sort();

      if (JSON.stringify(existingOptionIds.sort()) === JSON.stringify(currentOptionIds)) {
        // 同じ商品とオプションの組み合わせがある場合は数量を増やす
        if (item.quantity < product.stock) {
          item.quantity++;
        } else {
          error.value = '在庫数を超えています: ' + product.name;
        }

        calculateTotalPrice();
        resetOptionSelection();

        message.value = '商品とオプションがカートに追加されました';
        setTimeout(() => {
          message.value = '';
        }, 3000);

        return;
      }
    }
  }

  cart.value.push({
    id: product.id,
    name: product.name,
    image: product.image,
    price: totalItemPrice,
    quantity: 1,
    options: selectedOptions
  });

  calculateTotalPrice();
  resetOptionSelection();

  message.value = '商品とオプションがカートに追加されました';
  setTimeout(() => {
    message.value = '';
  }, 3000);
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
    error.value = 'カートが空です';
    setTimeout(() => {
      error.value = '';
    }, 3000);
    return;
  }

  // オプションがない場合は支払いポップアップを表示
  paymentAmount.value = 0;
  changeAmount.value = 0;
  showPaymentPopup.value = true;
};

// おつりを計算
const calculateChange = () => {
  changeAmount.value = paymentAmount.value - totalPrice.value;
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
    error.value = '支払い金額が不足しています';
    return;
  }

  try {
    // カートデータを整形
    const cartData = cart.value.map(item => ({
      id: item.id,
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
      showPaymentPopup.value = false;
      message.value = '注文が確定しました！';
      setTimeout(() => {
        message.value = '';
      }, 3000);
    } else {
      error.value = '注文の確定に失敗しました';
      showPaymentPopup.value = false;
      setTimeout(() => {
        error.value = '';
      }, 3000);
    }
  } catch (err) {
    console.error('注文確定エラー:', err);
    error.value = err.response?.data?.message || '注文の確定に失敗しました';
    showPaymentPopup.value = false;
    setTimeout(() => {
      error.value = '';
    }, 3000);
  }
};

onMounted(() => {
  loadProducts();
  detectDarkMode();
  watchSystemTheme();
  applyDarkMode();
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
</style>
