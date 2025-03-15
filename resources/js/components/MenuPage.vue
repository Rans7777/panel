<template>
  <div class="max-w-7xl mx-auto p-4 bg-white">
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-center my-6 text-gray-800 relative inline-block pb-2 after:content-[''] after:absolute after:bottom-0 after:left-1/2 after:-translate-x-1/2 after:w-1/2 after:h-[3px] after:bg-red-600">メニュー</h1>
    </div>

    <div v-if="disconnectWarning" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <i class="pi pi-exclamation-triangle text-yellow-400"></i>
        </div>
        <div class="ml-3">
          <p class="text-sm text-yellow-700">
            {{ connectionStatus }} - 自動的に再接続されます
          </p>
        </div>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center items-center h-64">
      <div class="w-9 h-9 border-4 border-gray-200 border-l-red-600 rounded-full animate-spin"></div>
    </div>

    <div v-else class="mt-8">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-8">
        <div v-for="product in products" :key="product.id" class="h-full rounded-lg overflow-hidden shadow-md bg-white max-w-[450px] mx-auto w-full transition-all duration-300 hover:translate-y-[-4px] hover:shadow-lg">
          <div class="w-full">
            <div class="h-[200px] flex justify-center items-center overflow-hidden bg-gray-100 relative">
              <img v-if="product.image" :src="'/storage/' + product.image" :alt="product.name" class="w-full h-full object-cover transition-transform duration-300 hover:scale-105" />
              <i v-else class="pi pi-image text-[5rem] text-gray-300"></i>
              <div v-if="product.stock <= 0" class="absolute top-0 right-0 bg-red-600/80 text-white py-1 px-3 font-bold text-sm rounded-bl-lg">
                <span>売り切れ</span>
              </div>
            </div>
          </div>
          <div class="p-4">
            <div class="text-[1.4rem] font-bold mb-2 text-gray-800">{{ product.name }}</div>

            <div v-if="product.description" class="mb-4 text-gray-600 text-sm">
              {{ product.description }}
            </div>

            <div v-if="product.allergens && product.allergens.length > 0" class="mb-4">
              <h3 class="text-sm font-bold mb-2 text-gray-600 flex items-center gap-2">
                <i class="pi pi-exclamation-circle text-red-500"></i>
                アレルギー情報
              </h3>
              <div class="flex flex-wrap gap-2">
                <span v-for="allergen in product.allergens" 
                      :key="allergen"
                      class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-red-50 text-red-700 border border-red-200">
                  {{ allergen }}
                </span>
              </div>
            </div>

            <div class="flex justify-between items-center mb-4">
              <div class="text-xl text-red-600 font-bold">{{ formatPrice(product.price) }}</div>
              <div v-if="product.stock > 0" class="text-sm flex items-center gap-1 text-green-600">
                <i class="pi pi-check-circle"></i> 在庫あり
              </div>
              <div v-else class="text-sm flex items-center gap-1 text-gray-500">
                <i class="pi pi-times-circle"></i> 在庫なし
              </div>
            </div>
            <div v-if="product.has_options" class="mt-4 border-t border-gray-200 pt-4">
              <h3 class="text-base font-bold mb-2 text-gray-600 relative inline-block pb-1 after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-full after:h-[2px] after:bg-gray-200">オプション</h3>
              <div v-for="option in product.options" :key="option.id" class="flex justify-between mb-1 py-2 border-b border-dashed border-gray-200 last:border-b-0">
                <span class="text-gray-600 text-[0.95rem]">{{ option.option_name }}</span>
                <span class="text-red-600 font-medium text-[0.95rem]">+{{ formatPrice(option.price) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';

export default {
  setup() {
    const products = ref([]);
    const loading = ref(true);
    let eventSource = null;
    let connectionStatus = ref('接続中');
    let disconnectWarning = ref(false);
    let remainingTime = ref(0);

    const setupEventSource = () => {
      if (eventSource) {
        eventSource.close();
      }

      eventSource = new EventSource(import.meta.env.VITE_PRODUCT_SSE_URL);
      disconnectWarning.value = false;

      eventSource.addEventListener('products', (event) => {
        try {
          const data = JSON.parse(event.data);
          products.value = data;
          loading.value = false;
        } catch (error) {
          console.error('製品データの解析に失敗しました:', error);
        }
      });

      eventSource.addEventListener('disconnect_warning', (event) => {
        try {
          const data = JSON.parse(event.data);
          disconnectWarning.value = true;
          remainingTime.value = parseInt(data.message.match(/\d+/)[0]);
          connectionStatus.value = `接続は${remainingTime.value}秒後に切断されます`;
        } catch (error) {
          console.error('切断警告の解析に失敗しました:', error);
        }
      });
      eventSource.addEventListener('close', (event) => {
        try {
          const data = JSON.parse(event.data);
          connectionStatus.value = 'サーバーとの接続が終了しました';
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
        connectionStatus.value = '接続エラー - 再接続中...';
        eventSource.close();
        setTimeout(() => {
          setupEventSource();
        }, 5000);
      };
    };

    const formatPrice = (price) => {
      return new Intl.NumberFormat('ja-JP', {
        style: 'currency',
        currency: 'JPY'
      }).format(price);
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
      products,
      loading,
      formatPrice,
      connectionStatus,
      disconnectWarning,
      remainingTime
    };
  }
};
</script>
