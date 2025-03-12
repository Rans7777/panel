<template>
  <div class="order-page" :class="{ 'dark-mode': isDarkMode }">
    <h1>注文ページ</h1>

    <div class="notifications-container">
      <div v-if="error" class="notification notification-error">
        <div class="notification-icon">✕</div>
        <div class="notification-content">{{ error }}</div>
      </div>
      <div v-if="message" class="notification notification-success">
        <div class="notification-icon">✓</div>
        <div class="notification-content">{{ message }}</div>
      </div>
    </div>

    <div class="order-cards">
      <!-- 商品カード一覧 -->
      <div
        v-for="product in products"
        :key="product.id"
        class="order-card"
        @click="handleProductClick(product.id)"
      >
        <div class="card-image">
          <img v-if="product.image" :src="'/storage/' + product.image" :alt="product.name" />
          <div v-else class="default-image">
            <img width="48" height="48" src="https://img.icons8.com/badges/48/shopping-basket.png" alt="shopping-basket"/>
          </div>
        </div>
        <div class="card-title">{{ product.name }}</div>
        <div class="card-price">¥{{ product.price }}</div>
      </div>
    </div>

    <!-- 注文テーブル -->
    <div class="cart-section">
      <h2>カート</h2>
      <div class="order-table">
        <table class="desktop-table">
          <thead>
            <tr>
              <th>商品名</th>
              <th>単価</th>
              <th>数量</th>
              <th>小計</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in cart" :key="index">
              <td class="product-name-cell">
                {{ item.name }}
                <div v-if="item.options && item.options.length > 0" class="option-info">
                  オプション:<br>
                  <span v-for="option in item.options" :key="option.id" class="option-item-cart">
                    {{ option.option_name }} (追加料金: ¥{{ option.price }})
                  </span>
                </div>
              </td>
              <td>¥{{ item.price }}</td>
              <td>
                <input 
                  type="number"
                  v-model.number="item.quantity"
                  min="1"
                  class="quantity-input"
                  @change="updateQuantity(index, item.quantity)"
                />
              </td>
              <td>¥{{ item.price * item.quantity }}</td>
              <td>
                <button @click="removeFromCart(index)" class="remove-button">削除</button>
              </td>
            </tr>
            <tr v-if="cart.length === 0">
              <td colspan="5" class="empty-cart">カートに商品がありません</td>
            </tr>
          </tbody>
        </table>

        <!-- モバイル用カート表示 -->
        <div class="mobile-cart">
          <div v-if="cart.length === 0" class="empty-cart">カートに商品がありません</div>
          <div v-for="(item, index) in cart" :key="index" class="mobile-cart-item">
            <div class="mobile-cart-header">
              <div class="mobile-cart-name">{{ item.name }}</div>
              <button @click="removeFromCart(index)" class="mobile-remove-button">×</button>
            </div>
            <div v-if="item.options && item.options.length > 0" class="mobile-option-info">
              <div class="mobile-option-label">オプション:</div>
              <div v-for="option in item.options" :key="option.id" class="mobile-option-item">
                {{ option.option_name }} (¥{{ option.price }})
              </div>
            </div>
            <div class="mobile-cart-footer">
              <div class="mobile-cart-price">¥{{ item.price }}</div>
              <div class="mobile-quantity-control">
                <button 
                  class="mobile-quantity-btn" 
                  @click="updateQuantity(index, Math.max(1, item.quantity - 1))"
                >−</button>
                <span class="mobile-quantity">{{ item.quantity }}</span>
                <button 
                  class="mobile-quantity-btn" 
                  @click="updateQuantity(index, item.quantity + 1)"
                >+</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 合計金額 -->
      <div class="order-total">
        <p>合計金額: ¥{{ totalPrice }}</p>
      </div>

      <!-- 注文ボタン -->
      <div class="order-actions">
        <button type="button" class="order-button" @click="showPaymentModal">注文を確定する</button>
      </div>
    </div>

    <!-- オプション選択ポップアップ -->
    <div v-if="showOptionsPopup" class="popup-overlay">
      <div class="popup-content">
        <h3>オプション選択</h3>
        <p>この商品にはオプションが用意されています。必要に応じて選択してください。</p>
        <div v-if="productOptions.length > 0" class="options-list">
          <div v-for="option in productOptions" :key="option.id" class="option-item">
            <input
              type="checkbox"
              :id="`option-${option.id}`"
              :value="option.id"
              v-model="selectedOptionIds"
            />
            <label :for="`option-${option.id}`">{{ option.option_name }} (追加料金: ¥{{ option.price }})</label>
          </div>
        </div>
        <div v-else>
          <p>この商品にはオプションがありません</p>
        </div>
        <div class="popup-actions">
          <button @click="cancelOptionSelection" class="cancel-button">キャンセル</button>
          <button @click="confirmOptionSelection" class="confirm-button">確定する</button>
        </div>
      </div>
    </div>

    <!-- 支払いポップアップ -->
    <div v-if="showPaymentPopup" class="popup-overlay">
      <div class="popup-content">
        <h3>お支払い</h3>
        <div class="payment-details">
          <p>合計金額: ¥{{ totalPrice }}</p>
          <div class="payment-input">
            <label for="payment-amount">お支払い金額:</label>
            <input 
              type="number" 
              id="payment-amount" 
              v-model.number="paymentAmount" 
              @input="validatePaymentInput"
              min="0"
              pattern="[0-9]*"
              inputmode="numeric"
            />
          </div>
          <p>おつり: ¥{{ changeAmount }}</p>
        </div>
        <div class="popup-actions">
          <button @click="showPaymentPopup = false" class="cancel-button">キャンセル</button>
          <button @click="confirmOrder" class="confirm-button" :disabled="paymentAmount < totalPrice">注文確定</button>
        </div>
      </div>
    </div>

    <div class="theme-toggle">
      <button @click="toggleDarkMode" class="theme-toggle-button">
        {{ isDarkMode ? '🌞 ライトモード' : '🌙 ダークモード' }}
      </button>
    </div>

    <div class="attribution">
      <p>Icon by <a href="https://icons8.com">Icons8</a></p>
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
.order-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
  color: #333 !important;
  background-color: #fff !important;
  transition: all 0.3s ease;
  min-height: 100vh;
}

:global(body),
:global(html) {
  background-color: #fff !important;
  margin: 0;
  padding: 0;
}

.order-page.dark-mode {
  color: #f0f0f0 !important;
  background-color: #1a1a1a !important;
}

:global(body.dark-mode),
:global(html.dark-mode) {
  background-color: #1a1a1a !important;
}

.order-page.dark-mode h1,
.order-page.dark-mode h2,
.order-page.dark-mode h3 {
  color: #f0f0f0 !important;
}

h1 {
  margin-bottom: 30px;
  font-size: 2.5rem;
  text-align: center;
}

.order-page.dark-mode .cart-section h2 {
  border-bottom-color: #444 !important;
}

.order-page.dark-mode .order-card {
  background-color: #2a2a2a !important;
  border-color: #444 !important;
}

.order-page.dark-mode .order-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 5px 15px rgba(255, 255, 255, 0.1);
  z-index: 10;
}

.order-page.dark-mode .card-title {
  color: #f0f0f0 !important;
}

.order-page.dark-mode .card-price {
  color: #ff6b6b !important;
}

.order-page.dark-mode .order-table {
  border-color: #444 !important;
  background-color: #2a2a2a !important;
}

.order-page.dark-mode table {
  background-color: #2a2a2a !important;
}

.order-page.dark-mode table th {
  background-color: #333 !important;
  color: #f0f0f0 !important;
  border-color: #444 !important;
}

.order-page.dark-mode table td {
  border-color: #444 !important;
  color: #f0f0f0 !important;
}

.order-page.dark-mode .quantity-input {
  background-color: #333 !important;
  color: #f0f0f0 !important;
  border-color: #555 !important;
}

.order-page.dark-mode .empty-cart {
  color: #aaa !important;
}

.order-page.dark-mode .popup-content {
  background-color: #2a2a2a !important;
  color: #f0f0f0 !important;
  border: 1px solid #444 !important;
}

.order-page.dark-mode .cancel-button {
  background-color: #444;
  color: #f0f0f0;
  border-color: #555;
}

.order-page.dark-mode .option-info,
.order-page.dark-mode .option-item-cart {
  color: #aaa;
}

.order-page.dark-mode .attribution {
  color: #888;
}

.order-page.dark-mode .remove-button {
  background-color: #c0392b;
}

.order-page.dark-mode .order-button {
  background-color: #2c8c30;
}

.order-page.dark-mode .order-button:hover {
  background-color: #1e6e22;
}

.order-page.dark-mode .confirm-button {
  background-color: #27ae60;
}

.order-page.dark-mode .confirm-button:disabled {
  background-color: #1e8449;
  opacity: 0.7;
}

.order-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  margin-top: 20px;
  margin-bottom: 30px;
  justify-content: flex-start;
}

.order-card {
  border: 1px solid #ddd;
  border-radius: 5px;
  padding: 15px;
  width: 180px;
  text-align: center;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
  background-color: #fff;
}

.order-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.1);
  z-index: 10;
}

.card-image {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100px;
}

.card-image img {
  width: 100px;
  height: 100px;
  object-fit: contain;
}

.card-title {
  margin-top: 10px;
  font-weight: bold;
  word-break: break-word;
  font-size: 1.2rem;
  line-height: 1.4;
}

.card-price {
  color: #e74c3c;
  font-weight: bold;
  margin-top: 5px;
  font-size: 1.3rem;
}

.cart-section {
  margin-top: 30px;
}

.cart-section h2 {
  margin-bottom: 15px;
  font-size: 1.5em;
  border-bottom: 1px solid #eee;
  padding-bottom: 10px;
}

.order-table {
  margin-bottom: 20px;
  overflow-x: auto;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.desktop-table {
  width: 100%;
  display: table;
}

.mobile-cart {
  display: none;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  padding: 12px;
  text-align: center;
  border-bottom: 1px solid #ddd;
}

th {
  background-color: #f8f8f8;
  font-weight: bold;
}

.product-name-cell {
  text-align: left;
}

.option-info {
  font-size: 0.9em;
  color: #666;
  margin-top: 5px;
}

.option-item-cart {
  display: block;
  color: #666;
  font-style: italic;
  margin-left: 10px;
}

.quantity-input {
  width: 60px;
  padding: 5px;
  text-align: center;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.empty-cart {
  text-align: center;
  padding: 20px;
  color: #777;
}

.order-total {
  text-align: right;
  font-size: 1.2em;
  font-weight: bold;
  margin: 20px 0;
}

.order-actions {
  display: flex;
  justify-content: flex-end;
}

.order-button {
  background-color: #4CAF50;
  color: white;
  padding: 12px 24px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 16px;
}

.order-button:hover {
  background-color: #45a049;
}

.remove-button {
  background-color: #e74c3c;
  color: white;
  border: none;
  border-radius: 4px;
  padding: 8px 16px;
  cursor: pointer;
}

.popup-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.popup-content {
  background-color: white;
  padding: 20px;
  border-radius: 5px;
  width: 90%;
  max-width: 500px;
  max-height: 80vh;
  overflow-y: auto;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.popup-content h3 {
  margin-top: 0;
  font-size: 1.5em;
  margin-bottom: 15px;
}

.options-list {
  margin: 15px 0;
}

.option-item {
  margin-bottom: 15px;
  display: flex;
  align-items: center;
}

.option-item input[type="checkbox"] {
  margin-right: 10px;
}

.popup-actions {
  display: flex;
  justify-content: space-between;
  margin-top: 20px;
}

.cancel-button {
  background-color: #f8f9fa;
  color: #212529;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 8px 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.cancel-button::before {
  content: "×";
  margin-right: 5px;
  font-weight: bold;
}

.confirm-button {
  background-color: #2ecc71;
  color: white;
  border: none;
  border-radius: 4px;
  padding: 8px 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.confirm-button::before {
  content: "✓";
  margin-right: 5px;
  font-weight: bold;
}

.payment-details {
  margin: 15px 0;
}

.payment-input {
  margin: 15px 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.payment-input input {
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  width: 150px;
}

.attribution {
  text-align: center;
  margin-top: 30px;
  color: #666;
  font-size: 0.8em;
}

.notifications-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 1100;
  width: 350px;
  max-width: 90vw;
}

.notification {
  display: flex;
  align-items: flex-start;
  padding: 12px 16px;
  margin-bottom: 10px;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  animation: slideIn 0.3s ease-out forwards;
  backdrop-filter: blur(10px);
}

.notification-success {
  background-color: rgba(52, 211, 153, 0.9);
  color: white;
  border-left: 4px solid #10b981;
}

.notification-error {
  background-color: rgba(248, 113, 113, 0.9);
  color: white;
  border-left: 4px solid #ef4444;
}

.notification-icon {
  margin-right: 12px;
  font-weight: bold;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.2);
}

.notification-content {
  flex: 1;
  font-size: 14px;
  line-height: 1.4;
}

.dark-mode .notification-success {
  background-color: rgba(0, 128, 0, 0.2);
  border-color: #4CAF50;
  color: #4CAF50;
}

.dark-mode .notification-error {
  background-color: rgba(255, 0, 0, 0.2);
  border-color: #ff6b6b;
  color: #ff6b6b;
}

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

.theme-toggle {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 100;
}

.theme-toggle-button {
  background-color: #4CAF50;
  color: white;
  border: none;
  border-radius: 50px;
  padding: 10px 15px;
  cursor: pointer;
  font-size: 14px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
  transition: all 0.3s ease;
}

.dark-mode .theme-toggle-button {
  background-color: #333;
}

.dark-mode .error-message {
  background-color: rgba(255, 0, 0, 0.1);
  color: #ff6b6b;
}

.dark-mode .success-message {
  background-color: rgba(0, 255, 0, 0.1);
  color: #4CAF50;
}

.dark-mode .payment-input input {
  background-color: #333;
  color: #f0f0f0;
  border-color: #555;
}

@media (max-width: 768px) {
  .order-page {
    padding: 10px;
  }

  h1 {
    font-size: 1.5rem;
    text-align: center;
  }

  .order-cards {
    gap: 10px;
    justify-content: center;
  }

  .order-card {
    width: calc(50% - 10px);
    padding: 10px;
  }

  .card-image img {
    width: 80px;
    height: 80px;
  }

  .card-title {
    font-size: 0.9rem;
  }

  .desktop-table {
    display: none;
  }

  .mobile-cart {
    display: block;
  }

  .mobile-cart-item {
    background-color: #f9f9f9;
    border-radius: 8px;
    margin-bottom: 12px;
    padding: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }

  .dark-mode .mobile-cart-item {
    background-color: #2a2a2a;
    border: 1px solid #444;
  }

  .mobile-cart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
  }

  .mobile-cart-name {
    font-weight: bold;
    font-size: 1rem;
  }

  .mobile-remove-button {
    background-color: #e74c3c;
    color: white;
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    cursor: pointer;
  }

  .mobile-option-info {
    background-color: rgba(0, 0, 0, 0.03);
    padding: 8px;
    border-radius: 4px;
    margin-bottom: 8px;
    font-size: 0.9rem;
  }

  .dark-mode .mobile-option-info {
    background-color: rgba(255, 255, 255, 0.05);
  }

  .mobile-option-label {
    font-weight: bold;
    margin-bottom: 4px;
  }

  .mobile-option-item {
    padding-left: 8px;
    margin-bottom: 4px;
    color: #666;
  }

  .dark-mode .mobile-option-item {
    color: #aaa;
  }

  .mobile-cart-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
  }

  .mobile-cart-price {
    font-weight: bold;
    color: #e74c3c;
  }

  .dark-mode .mobile-cart-price {
    color: #ff6b6b;
  }

  .mobile-quantity-control {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .mobile-quantity-btn {
    background-color: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    cursor: pointer;
  }

  .dark-mode .mobile-quantity-btn {
    background-color: #333;
    border-color: #555;
    color: #f0f0f0;
  }

  .mobile-quantity {
    font-weight: bold;
    min-width: 24px;
    text-align: center;
  }

  .order-button {
    width: 100%;
    padding: 10px;
  }

  .popup-content {
    width: 95%;
    padding: 15px;
  }

  .payment-input {
    flex-direction: column;
    align-items: flex-start;
  }

  .payment-input input {
    width: 100%;
  }

  .theme-toggle {
    bottom: 10px;
    right: 10px;
  }

  .theme-toggle-button {
    padding: 8px 12px;
    font-size: 12px;
  }
}

@media (max-width: 480px) {
  .order-card {
    width: calc(50% - 5px);
    margin-bottom: 10px;
    padding: 8px;
  }

  .card-image img {
    width: 70px;
    height: 70px;
  }

  .card-title {
    font-size: 0.8rem;
    margin-top: 5px;
    margin-bottom: 5px;
  }

  .card-price {
    font-size: 0.9rem;
    margin-top: 3px;
  }

  th:nth-child(4), 
  td:nth-child(4) {
    display: none;
  }

  .product-name-cell {
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .option-info {
    font-size: 0.8rem;
  }

  .option-item-cart {
    font-size: 0.8rem;
  }

  .order-total {
    text-align: center;
  }

  .popup-actions {
    flex-direction: column;
    gap: 10px;
  }

  .popup-actions button {
    width: 100%;
  }
}

@media (orientation: landscape) and (max-height: 500px) {
  .order-cards {
    max-height: 40vh;
    overflow-y: auto;
  }

  .popup-content {
    max-height: 80vh;
  }
}
</style>
