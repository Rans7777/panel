<template>
  <div class="min-h-screen transition-all duration-300" :class="{ 'bg-gray-900 text-gray-100': isDarkMode, 'bg-white text-gray-800': !isDarkMode }">
    <div class="order-history max-w-7xl mx-auto p-4">
      <h2 class="text-2xl font-bold mb-4" :class="{ 'text-gray-100': isDarkMode, 'text-gray-800': !isDarkMode }">注文履歴</h2>

      <div v-if="loading" class="flex justify-center items-center h-64">
        <div class="w-9 h-9 border-4 rounded-full animate-spin" :class="{ 'border-gray-700 border-l-red-500': isDarkMode, 'border-gray-200 border-l-red-600': !isDarkMode }"></div>
      </div>

      <div v-if="disconnectWarning" class="border-l-4 p-4 mb-4" :class="{ 'bg-yellow-900/30 border-yellow-600 text-yellow-200': isDarkMode, 'bg-yellow-50 border-yellow-400 text-yellow-700': !isDarkMode }">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <i class="pi pi-exclamation-triangle" :class="{ 'text-yellow-400': !isDarkMode, 'text-yellow-300': isDarkMode }"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm">
              {{ connectionStatus }}
            </p>
          </div>
        </div>
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
    const productCache = ref({});
    let disconnectWarning = ref(false);
    let connectionStatus = ref('接続中');
    let eventSource = null;
    let warningTimer = null;
    let remainingTime = ref(0);
    const isDarkMode = ref(false);

    const showWarning = (message, duration = 0) => {
      if (warningTimer) {
        clearTimeout(warningTimer);
        warningTimer = null;
      }
      disconnectWarning.value = true;
      connectionStatus.value = message;
      if (duration > 0) {
        warningTimer = setTimeout(() => {
          disconnectWarning.value = false;
          warningTimer = null;
        }, duration);
      }
    };

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
        productCache.value[productId] = response.data.data;
        return response.data.data;
      } catch (error) {
        showWarning(`商品情報の取得に失敗しました (ID: ${productId}):`, 0);
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

      showWarning('接続中...', 0);

      axios.get('/api/create-access-token')
        .then(response => {
          const token = response.data.access_token;

          const fetchOrdersWithToken = async () => {
            try {
              const response = await fetch(import.meta.env.VITE_ORDER_SSE_URL, {
                headers: {
                  'Authorization': `Bearer ${token}`
                }
              });

              if (!response.ok) {
                throw new Error(`HTTP error ${response.status}`);
              }

              const reader = response.body.getReader();
              const decoder = new TextDecoder();
              let buffer = '';

              while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n\n');
                for (const line of lines) {
                  if (!line.trim()) continue;
                  const eventLines = line.split('\n');
                  let eventName = 'message';
                  let data = '';
                  for (const eventLine of eventLines) {
                    if (eventLine.startsWith('event:')) {
                      eventName = eventLine.slice(6).trim();
                    } else if (eventLine.startsWith('data:')) {
                      data = eventLine.slice(5).trim();
                    }
                  }
                  if (eventName === 'orders') {
                    try {
                      const parsedData = JSON.parse(data);
                      const processedData = await processOrdersWithProductInfo(parsedData);
                      orders.value = processedData;
                      loading.value = false;
                      if (disconnectWarning.value && connectionStatus.value === '接続中...') {
                        showWarning('接続成功', 3000);
                      }
                    } catch (error) {
                      showWarning('注文データの解析に失敗しました:', 0);
                    }
                  } else if (eventName === 'disconnect_warning') {
                    try {
                      const parsedData = JSON.parse(data);
                      remainingTime.value = parseInt(parsedData.message.match(/\d+/)[0]);
                      if (remainingTime.value <= 10) {
                        reader.cancel();
                        setTimeout(() => {
                          setupEventSource();
                        }, 1000);
                        return;
                      }
                    } catch (error) {
                      showWarning('切断警告の解析に失敗しました:', 0);
                    }
                  } else if (eventName === 'close') {
                    setTimeout(() => {
                      setupEventSource();
                    }, 5000);
                    return;
                  }
                }
              }
            } catch (error) {
              showWarning('サーバーとの接続に失敗しました。', 5000);
              setTimeout(() => {
                setupEventSource();
              }, 5000);
            }
          };
          fetchOrdersWithToken();
        })
        .catch(() => {
          showWarning('APIトークンの取得に失敗しました。', 5000);
          setTimeout(() => {
            setupEventSource();
          }, 5000);
        });
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
        const parsedOptions = JSON.parse(optionsStr);
        if (typeof parsedOptions === 'string') {
          return JSON.parse(parsedOptions);
        }
        return parsedOptions;
      } catch (error) {
        showWarning('オプションの解析に失敗しました:', 0);
        return [];
      }
    };

    const getProductName = (productId) => {
      return productCache.value[productId]?.name || `${productId}`;
    };

    const getProductOptions = (productId) => {
      return productCache.value[productId]?.options || [];
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
      disconnectWarning,
      connectionStatus,
      groupedOrders,
      formatDate,
      calculateGroupTotal,
      remainingTime,
      parseOptions,
      getProductName,
      getProductOptions,
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
