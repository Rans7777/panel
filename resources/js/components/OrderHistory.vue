<template>
  <div class="min-h-screen transition-all duration-300" :class="{ 'bg-gray-900 text-gray-100': isDarkMode, 'bg-white text-gray-800': !isDarkMode }">
    <div class="order-history max-w-7xl mx-auto p-4">
      <h2 class="text-2xl font-bold mb-4" :class="{ 'text-gray-100': isDarkMode, 'text-gray-800': !isDarkMode }">注文履歴</h2>

      <div v-if="loading" class="flex justify-center items-center h-64">
        <div class="w-9 h-9 border-4 rounded-full animate-spin" :class="{ 'border-gray-700 border-l-red-500': isDarkMode, 'border-gray-200 border-l-red-600': !isDarkMode }"></div>
      </div>
      <div v-else-if="error" class="text-red-600">
        <p>{{ error }}</p>
      </div>
      <div v-else>
        <div v-if="groupedOrders.length === 0" class="text-center" :class="{ 'text-gray-400': isDarkMode, 'text-gray-600': !isDarkMode }">
          <p>注文履歴がありません</p>
        </div>
        <div v-else class="space-y-6">
          <div v-for="orderGroup in groupedOrders" :key="orderGroup.uuid" class="border rounded-lg p-6 shadow-sm" :class="{ 'bg-gray-800 border-gray-700': isDarkMode, 'bg-white border-gray-200': !isDarkMode }">
            <div class="border-b pb-4 mb-4" :class="{ 'border-gray-700': isDarkMode, 'border-gray-200': !isDarkMode }">
              <p :class="{ 'text-gray-400': isDarkMode, 'text-gray-600': !isDarkMode }">注文日: {{ formatDate(orderGroup.created_at) }}</p>
              <p :class="{ 'text-gray-400': isDarkMode, 'text-gray-600': !isDarkMode }">注文番号: {{ orderGroup.uuid }}</p>
            </div>
            <div class="space-y-4">
              <div v-for="order in orderGroup.orders" :key="order.product_id" class="flex justify-between items-start border-b last:border-b-0 pb-4 last:pb-0" :class="{ 'border-gray-700': isDarkMode, 'border-gray-200': !isDarkMode }">
                <div>
                  <h3 class="font-semibold" :class="{ 'text-gray-100': isDarkMode, 'text-gray-800': !isDarkMode }">商品名: {{ getProductName(order.product_id) }}</h3>
                  <p :class="{ 'text-gray-400': isDarkMode, 'text-gray-700': !isDarkMode }" class="mt-2">数量: {{ order.quantity }}個</p>
                  <div v-if="order.options" class="mt-2 text-sm" :class="{ 'text-gray-400': isDarkMode, 'text-gray-600': !isDarkMode }">
                    <p class="font-medium">オプション:</p>
                    <ul class="list-disc list-inside pl-2">
                      <li v-for="(option, index) in parseOptions(order.options)" :key="index">
                        {{ option.option_name }}
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <button 
      @click="toggleDarkMode"
      class="fixed bottom-5 right-5 px-4 py-2 rounded-full shadow-lg transition-colors text-white"
      :class="{ 'bg-gray-700 hover:bg-gray-600': isDarkMode, 'bg-green-600 hover:bg-green-700': !isDarkMode }"
    >
      <i :class="isDarkMode ? 'pi pi-sun' : 'pi pi-moon'" class="mr-2"></i>
      {{ isDarkMode ? 'ライトモード' : 'ダークモード' }}
    </button>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

export default {
  name: 'OrderHistory',
  setup() {
    const orders = ref([]);
    const loading = ref(true);
    const error = ref(null);
    const productCache = ref({});
    let eventSource = null;
    let remainingTime = ref(0);
    const isDarkMode = ref(false);

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

    const toggleDarkMode = () => {
      isDarkMode.value = !isDarkMode.value;
      localStorage.setItem('theme', isDarkMode.value ? 'dark' : 'light');
      applyDarkMode();
    };

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

    const fetchProductInfo = async (productId) => {
      if (productCache.value[productId]) {
        return productCache.value[productId];
      }
      
      try {
        const response = await axios.get(`/api/products/${productId}`);
        productCache.value[productId] = response.data;
        return response.data;
      } catch (error) {
        console.error(`商品情報の取得に失敗しました (ID: ${productId}):`, error);
        return null;
      }
    };

    const processOrdersWithProductInfo = async (ordersData) => {
      const processedOrders = [...ordersData];
      
      const productIds = new Set(processedOrders.map(order => order.product_id));
      
      const fetchPromises = Array.from(productIds).map(id => fetchProductInfo(id));
      await Promise.all(fetchPromises);
      
      return processedOrders;
    };

    const setupEventSource = () => {
      if (eventSource) {
        eventSource.close();
      }

      const sseUrl = import.meta.env.VITE_ORDER_SSE_URL || 'http://localhost:8000/api/orders/stream';
      eventSource = new EventSource(sseUrl);

      eventSource.addEventListener('orders', async (event) => {
        try {
          const data = JSON.parse(event.data);
          const processedData = await processOrdersWithProductInfo(data);
          orders.value = processedData;
          loading.value = false;
        } catch (error) {
          console.error('注文データの解析に失敗しました:', error);
          error.value = '注文履歴の取得に失敗しました。';
        }
      });

      eventSource.addEventListener('disconnect_warning', (event) => {
        try {
          const data = JSON.parse(event.data);
          remainingTime.value = parseInt(data.message.match(/\d+/)[0]);
        } catch (error) {
          console.error('切断警告の解析に失敗しました:', error);
        }
      });

      eventSource.addEventListener('close', (event) => {
        try {
          const data = JSON.parse(event.data);
          eventSource.close();
          setTimeout(() => {
            setupEventSource();
          }, 5000);
        } catch (error) {
          console.error('接続終了メッセージの解析に失敗しました:', error);
        }
      });

      eventSource.onerror = (error) => {
        console.error('SSE接続エラー:', error);
        eventSource.close();
        setTimeout(() => {
          setupEventSource();
        }, 5000);
      };
    };

    const groupedOrders = computed(() => {
      const groups = orders.value.reduce((acc, order) => {
        if (!acc[order.uuid]) {
          acc[order.uuid] = {
            uuid: order.uuid,
            created_at: order.created_at,
            orders: []
          };
        }
        acc[order.uuid].orders.push(order);
        return acc;
      }, {});

      return Object.values(groups).sort((a, b) => 
        new Date(b.created_at) - new Date(a.created_at)
      );
    });

    const formatDate = (date) => {
      return new Date(date).toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    };

    const calculateGroupTotal = (orders) => {
      return orders.reduce((sum, order) => sum + order.total_price, 0);
    };

    const parseOptions = (optionsStr) => {
      if (!optionsStr) return [];
      try {
        return JSON.parse(optionsStr);
      } catch (error) {
        console.error('オプションの解析に失敗しました:', error);
        return [];
      }
    };

    const getProductName = (productId) => {
      return productCache.value[productId]?.name || `商品ID: ${productId}`;
    };

    onMounted(() => {
      setupEventSource();
      detectDarkMode();
      watchSystemTheme();
      applyDarkMode();
    });

    onUnmounted(() => {
      if (eventSource) {
        eventSource.close();
      }
    });

    return {
      orders,
      loading,
      error,
      groupedOrders,
      formatDate,
      calculateGroupTotal,
      remainingTime,
      parseOptions,
      getProductName,
      isDarkMode,
      toggleDarkMode
    };
  }
};
</script>

<style scoped>
.order-history {
  max-width: 800px;
  margin: 0 auto;
  padding: 1rem;
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
</style>
