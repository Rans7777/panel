<template>
  <div class="order-history">
    <h2 class="text-2xl font-bold mb-4">注文履歴</h2>

    <div v-if="loading" class="flex justify-center items-center h-64">
      <div class="w-9 h-9 border-4 border-gray-200 border-l-red-600 rounded-full animate-spin"></div>
    </div>
    <div v-else-if="error" class="text-red-600">
      <p>{{ error }}</p>
    </div>
    <div v-else>
      <div v-if="groupedOrders.length === 0" class="text-center">
        <p>注文履歴がありません</p>
      </div>
      <div v-else class="space-y-6">
        <div v-for="orderGroup in groupedOrders" :key="orderGroup.uuid" class="border rounded-lg p-6 shadow-sm">
          <div class="border-b pb-4 mb-4">
            <p class="text-gray-600">注文日: {{ formatDate(orderGroup.created_at) }}</p>
            <p class="text-gray-600">注文番号: {{ orderGroup.uuid }}</p>
          </div>
          <div class="space-y-4">
            <div v-for="order in orderGroup.orders" :key="order.product_id" class="flex justify-between items-start border-b last:border-b-0 pb-4 last:pb-0">
              <div>
                <h3 class="font-semibold">商品名: {{ getProductName(order.product_id) }}</h3>
                <p class="text-gray-700 mt-2">数量: {{ order.quantity }}個</p>
                <div v-if="order.options" class="mt-2 text-sm text-gray-600">
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
      getProductName
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
</style>
