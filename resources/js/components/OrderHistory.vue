<template>
  <div class="order-history">
    <h2 class="text-2xl font-bold mb-4">注文履歴</h2>
    <div v-if="loading" class="text-center">
      <p>読み込み中...</p>
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
            <div v-for="order in orderGroup.orders" :key="order.id" class="flex justify-between items-start border-b last:border-b-0 pb-4 last:pb-0">
              <div>
                <h3 class="font-semibold">{{ order.product.name }}</h3>
                <p class="text-gray-700 mt-2">数量: {{ order.quantity }}個</p>
                <div v-if="order.options" class="mt-2 text-sm text-gray-600">
                  <p class="font-medium">オプション:</p>
                  <ul class="list-disc list-inside pl-2">
                    <li v-for="option in JSON.parse(order.options)" :key="option.id">
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
import axios from 'axios';

export default {
  name: 'OrderHistory',
  data() {
    return {
      orders: [],
      loading: true,
      error: null
    };
  },
  computed: {
    groupedOrders() {
      const groups = this.orders.reduce((acc, order) => {
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
    }
  },
  methods: {
    async fetchOrders() {
      try {
        this.loading = true;
        const response = await axios.get('/api/order-history');
        this.orders = response.data;
      } catch (err) {
        this.error = '注文履歴の取得に失敗しました。';
        console.error('Error fetching orders:', err);
      } finally {
        this.loading = false;
      }
    },
    formatDate(date) {
      return new Date(date).toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    },
    calculateGroupTotal(orders) {
      return orders.reduce((sum, order) => sum + order.total_price, 0);
    }
  },
  mounted() {
    this.fetchOrders();
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
