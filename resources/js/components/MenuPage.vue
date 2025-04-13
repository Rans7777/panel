<template>
  <div class="min-h-screen transition-all duration-300 overflow-hidden" 
       :class="{ 
         'bg-gray-900 text-gray-100': isDarkMode && !isRainbowMode, 
         'bg-white text-gray-800': !isDarkMode && !isRainbowMode,
         'rainbow-bg': isRainbowMode 
       }">
    <div class="max-w-7xl mx-auto p-4">
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-center my-6 relative inline-block pb-2 after:content-[''] after:absolute after:bottom-0 after:left-1/2 after:-translate-x-1/2 after:w-1/2 after:h-[3px]" 
            :class="{ 
              'text-gray-100 after:bg-red-600': isDarkMode && !isRainbowMode, 
              'text-gray-800 after:bg-red-600': !isDarkMode && !isRainbowMode,
              'rainbow-text': isRainbowMode,
              'raging-text': isRagingMode,
              'fly-element': isFlyMode,
              'rotate-3d': isRotate3dMode 
            }">メニュー</h1>
      </div>

      <div v-if="disconnectWarning" class="border-l-4 p-4 mb-4" 
           :class="{ 
             'bg-yellow-900/30 border-yellow-600 text-yellow-200': isDarkMode && !isRainbowMode, 
             'bg-yellow-50 border-yellow-400 text-yellow-700': !isDarkMode && !isRainbowMode,
             'rainbow-border': isRainbowMode 
           }">
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

      <div v-if="loading" class="flex justify-center items-center h-64">
        <div class="w-9 h-9 border-4 rounded-full animate-spin" 
             :class="{ 
               'border-gray-700 border-l-red-500': isDarkMode && !isRainbowMode, 
               'border-gray-200 border-l-red-600': !isDarkMode && !isRainbowMode,
               'rainbow-border': isRainbowMode 
             }"></div>
      </div>

      <div v-else class="mt-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-8">
          <div v-for="product in products" :key="product.id" 
               class="h-full rounded-lg overflow-hidden shadow-md max-w-[450px] mx-auto w-full transition-all duration-300 hover:translate-y-[-4px] hover:shadow-lg" 
               :class="{ 
                 'bg-gray-800 shadow-gray-900/30': isDarkMode && !isRainbowMode, 
                 'bg-white': !isDarkMode && !isRainbowMode,
                 'animate-spin-slow': isHiddenMode === 'rotate',
                 'rainbow-border': isRainbowMode,
                 'rainbow-bg': isRainbowMode,
                 'raging-text': isRagingMode,
                 'fly-element': isFlyMode,
                 'rotate-3d': isRotate3dMode 
               }">
            <div class="w-full">
              <div class="h-[200px] flex justify-center items-center overflow-hidden relative" :class="{ 'bg-gray-700': isDarkMode, 'bg-gray-100': !isDarkMode }">
                <img v-if="product.image" :src="'/storage/' + product.image" :alt="product.name" class="w-full h-full object-cover transition-transform duration-300 hover:scale-105" />
                <i v-else class="pi pi-image text-[5rem]" :class="{ 'text-gray-600': isDarkMode, 'text-gray-300': !isDarkMode }"></i>
                <div v-if="product.stock <= 0" class="absolute top-0 right-0 bg-red-600/80 text-white py-1 px-3 font-bold text-sm rounded-bl-lg">
                  <span>売り切れ</span>
                </div>
              </div>
            </div>
            <div class="p-4">
              <div class="text-[1.4rem] font-bold mb-2" 
                   :class="{ 
                     'text-gray-100': isDarkMode && !isRainbowMode, 
                     'text-gray-800': !isDarkMode && !isRainbowMode,
                     'text-white': isRainbowMode,
                     'raging-text': isRagingMode 
                   }">{{ product.name }}</div>

              <div v-if="product.description" class="mb-4 text-sm" 
                   :class="{ 
                     'text-gray-400': isDarkMode && !isRainbowMode, 
                     'text-gray-600': !isDarkMode && !isRainbowMode,
                     'text-white': isRainbowMode,
                     'raging-text': isRagingMode 
                   }">
                {{ product.description }}
              </div>

              <div v-if="product.allergens && product.allergens.length > 0" class="mb-4">
                <h3 class="text-sm font-bold mb-2 flex items-center gap-2" :class="{ 'text-gray-400': isDarkMode, 'text-gray-600': !isDarkMode }">
                  <i class="pi pi-exclamation-circle text-red-500"></i>
                  アレルギー情報
                </h3>
                <div class="flex flex-wrap gap-2">
                  <span v-for="allergen in product.allergens" 
                        :key="allergen"
                        class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium" 
                        :class="{ 'bg-red-900/30 text-red-300 border border-red-800': isDarkMode, 'bg-red-50 text-red-700 border border-red-200': !isDarkMode }">
                    {{ allergen }}
                  </span>
                </div>
              </div>

              <div class="flex justify-between items-center mb-4">
                <div class="text-xl font-bold" 
                     :class="{ 
                       'text-red-400': isDarkMode && !isRainbowMode, 
                       'text-red-600': !isDarkMode && !isRainbowMode,
                       'text-white': isRainbowMode,
                       'raging-text': isRagingMode 
                     }">{{ General.formatPrice(product.price) }}</div>
                <div v-if="product.stock > 0" class="text-sm flex items-center gap-1" :class="{ 'text-green-400': isDarkMode, 'text-green-600': !isDarkMode }">
                  <span v-if="product.stock <= 10" class="text-yellow-500">
                    <i class="pi pi-exclamation-triangle"></i> 残りわずか
                  </span>
                  <span v-else>
                    <i class="pi pi-check-circle"></i> 在庫あり
                  </span>
                </div>
                <div v-else class="text-sm flex items-center gap-1 text-gray-500">
                  <i class="pi pi-times-circle"></i> 在庫なし
                </div>
              </div>
              <div v-if="product.has_options" class="mt-4 pt-4" :class="{ 'border-t border-gray-700': isDarkMode, 'border-t border-gray-200': !isDarkMode }">
                <h3 class="text-base font-bold mb-2 relative inline-block pb-1 after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-full after:h-[2px]" :class="{ 'text-gray-400 after:bg-gray-700': isDarkMode, 'text-gray-600 after:bg-gray-200': !isDarkMode }">オプション</h3>
                <div v-for="option in product.options" :key="option.id" class="flex justify-between mb-1 py-2 last:border-b-0" :class="{ 'border-b border-dashed border-gray-700': isDarkMode, 'border-b border-dashed border-gray-200': !isDarkMode }">
                  <span :class="{ 'text-gray-400': isDarkMode, 'text-gray-600': !isDarkMode }" class="text-[0.95rem]">{{ option.option_name }}</span>
                  <span :class="{ 'text-red-400': isDarkMode, 'text-red-600': !isDarkMode }" class="font-medium text-[0.95rem]">+{{ General.formatPrice(option.price) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <button 
        @click="toggleDarkMode"
        class="fixed bottom-5 right-5 px-4 py-2 rounded-full shadow-lg transition-colors text-white"
        :class="{ 
          'bg-gray-700 hover:bg-gray-600': isDarkMode && !isRainbowMode, 
          'bg-green-600 hover:bg-green-700': !isDarkMode && !isRainbowMode,
          'rainbow-bg': isRainbowMode,
          'raging-text': isRagingMode,
          'fly-element': isFlyMode,
          'rotate-3d': isRotate3dMode 
        }"
      >
        <i :class="isDarkMode ? 'pi pi-sun' : 'pi pi-moon'" class="mr-2"></i>
        {{ isDarkMode ? 'ライトモード' : 'ダークモード' }}
      </button>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import Cache from '../utils/Cache';
import API from '../utils/API';
import General from '../utils/General';

export default {
  setup() {
    const products = ref([]);
    const loading = ref(true);
    let eventSource = null;
    let connectionStatus = ref('接続中');
    let disconnectWarning = ref(false);
    let remainingTime = ref(0);
    const isDarkMode = ref(false);
    let warningTimer = null;
    let currentToken = ref(null);
    let tokenRetryCount = ref(0);
    let tokenRetryTimer = null;
    const api = new API();
    const isHiddenMode = ref('');
    const isRainbowMode = ref(false);
    const isRagingMode = ref(false);
    const isFlyMode = ref(false);
    const isRotate3dMode = ref(false);

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

    const tokenCache = new Cache(5 * 60 * 1000, true, true, 'token_');
    const tokenValidityCache = new Cache(30 * 1000, true, true, 'tokenvalidity_');

    const setupEventSource = async () => {
      if (eventSource) {
        eventSource.close();
      }
      if (tokenRetryTimer) {
        clearTimeout(tokenRetryTimer);
        tokenRetryTimer = null;
      }
      showWarning('接続中...', 0);
      let token = await tokenCache.get('MenuPageToken');
      if (!token) {
        token = await api.getAccessToken();
        if (token && typeof token !== 'number') {
          await tokenCache.set('MenuPageToken', token);
        }
      }
      if (token === 429) {
        showWarning('アクセス制限中です。しばらく待ってから画面を更新してください。', 0);
        return;
      }
      if (typeof token === 'number') {
        const retryDelay = Math.min(30000 + (tokenRetryCount.value * 10000), 60000);
        showWarning(`APIトークンの取得に失敗しました - ${Math.round(retryDelay/1000)}秒後に再試行します`, 0);
        tokenRetryTimer = setTimeout(() => {
          setupEventSource();
        }, retryDelay);
        return;
      }

      const fetchProductsWithToken = async () => {
        try {
          const response = await fetch(import.meta.env.VITE_SSE_URL+'/api/products/stream', {
            headers: {
              'Authorization': `Bearer ${token}`
            }
          });
          if (!response.ok) {
            if (response.status === 401) {
              currentToken.value = null;
              await tokenCache.del('MenuPageToken');
            }
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
            buffer = lines.pop() || '';
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
              if (eventName === 'products') {
                try {
                  const parsedData = JSON.parse(data);
                  products.value = parsedData;
                  loading.value = false;
                  if (disconnectWarning.value && connectionStatus.value === '接続中...') {
                    showWarning('接続成功', 3000);
                  }
                } catch (error) {
                  showWarning('製品データの解析に失敗しました:', 0);
                }
              } else if (eventName === 'connected') {
                showWarning('接続成功', 3000);
              } else if (eventName === 'disconnect_warning') {
                try {
                  const parsedData = JSON.parse(data);
                  remainingTime.value = parseInt(parsedData.message.match(/\d+/)[0]);
                  showWarning(`接続は${remainingTime.value}秒後に切断されます - 自動的に再接続されます`, 0);
                  if (remainingTime.value <= 10) {
                    reader.cancel();
                    showWarning('再接続中...', 0);
                    currentToken.value = null;
                    setTimeout(() => {
                      setupEventSource();
                    }, 1000);
                    return;
                  }
                } catch (error) {
                  showWarning('切断警告の解析に失敗しました:', 0);
                }
              } else if (eventName === 'close') {
                showWarning('サーバーとの接続が終了しました - 再接続中...', 0);
                setTimeout(() => {
                  setupEventSource();
                }, 5000);
                return;
              }
            }
          }
        } catch (error) {
          const isTokenValid = api.validateTokenAfterError(token, tokenValidityCache, 'MenuPageTokenValidity');
          if (!isTokenValid) {
            currentToken.value = null;
            await tokenCache.del('MenuPageToken');
          }
          showWarning('接続エラー - 再接続中...', 0);
          setTimeout(() => {
            setupEventSource();
          }, 5000);
        }
      };
      fetchProductsWithToken();
    };

    const checkHiddenMode = () => {
      const urlParams = new URLSearchParams(window.location.search);
      const hiddenParam = urlParams.get('hidden');
      isHiddenMode.value = hiddenParam;
      isRainbowMode.value = hiddenParam === 'rainbow';
      isRagingMode.value = hiddenParam === 'raging';
      isFlyMode.value = hiddenParam === 'fly';
      isRotate3dMode.value = hiddenParam === 'rotate3d';
    };

    onMounted(() => {
      setupEventSource();
      detectDarkMode();
      watchSystemTheme();
      applyDarkMode();
      checkHiddenMode();
    });

    onUnmounted(async () => {
      if (eventSource) {
        eventSource.close();
        await tokenCache.clear();
        await tokenValidityCache.clear();
      }
      if (warningTimer) {
        clearTimeout(warningTimer);
      }
      if (tokenRetryTimer) {
        clearTimeout(tokenRetryTimer);
      }
    });

    return {
      products,
      loading,
      connectionStatus,
      disconnectWarning,
      remainingTime,
      isDarkMode,
      toggleDarkMode,
      General,
      isHiddenMode,
      isRainbowMode,
      isRagingMode,
      isFlyMode,
      isRotate3dMode
    };
  }
};
</script>

<style>
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

@keyframes spin-slow {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.animate-spin-slow {
  animation: spin-slow 10s linear infinite;
}

@keyframes rainbow-text {
  0% { color: #ff0000; }
  16.666% { color: #ff8800; }
  33.333% { color: #ffff00; }
  50% { color: #00ff00; }
  66.666% { color: #0000ff; }
  83.333% { color: #ff00ff; }
  100% { color: #ff0000; }
}

.rainbow-text {
  animation: rainbow-text 3s linear infinite;
}

@keyframes rainbow-bg {
  0% { background: linear-gradient(45deg, #ff0000, #ff8800); }
  16.666% { background: linear-gradient(45deg, #ff8800, #ffff00); }
  33.333% { background: linear-gradient(45deg, #ffff00, #00ff00); }
  50% { background: linear-gradient(45deg, #00ff00, #0000ff); }
  66.666% { background: linear-gradient(45deg, #0000ff, #ff00ff); }
  83.333% { background: linear-gradient(45deg, #ff00ff, #ff0000); }
  100% { background: linear-gradient(45deg, #ff0000, #ff8800); }
}

.rainbow-bg {
  animation: rainbow-bg 3s linear infinite;
  background-size: 200% 200%;
  color: white !important;
}

@keyframes rainbow-border {
  0% { border-color: #ff0000; }
  16.666% { border-color: #ff8800; }
  33.333% { border-color: #ffff00; }
  50% { border-color: #00ff00; }
  66.666% { border-color: #0000ff; }
  83.333% { border-color: #ff00ff; }
  100% { border-color: #ff0000; }
}

.rainbow-border {
  border: 4px solid transparent;
  animation: rainbow-border 3s linear infinite;
}

@keyframes shake {
  0%, 100% { transform: translate(0, 0) rotate(0deg); }
  10% { transform: translate(-2px, -2px) rotate(-1deg); }
  20% { transform: translate(2px, 2px) rotate(1deg); }
  30% { transform: translate(-2px, 2px) rotate(-1deg); }
  40% { transform: translate(2px, -2px) rotate(1deg); }
  50% { transform: translate(-2px, 2px) rotate(-1deg); }
  60% { transform: translate(2px, -2px) rotate(1deg); }
  70% { transform: translate(-2px, -2px) rotate(-1deg); }
  80% { transform: translate(2px, 2px) rotate(1deg); }
  90% { transform: translate(-2px, 2px) rotate(-1deg); }
}

.raging-text {
  animation: shake 0.5s infinite;
  display: inline-block;
  transform-origin: center;
}

.raging-text:hover {
  animation: shake 0.2s infinite;
}

@keyframes fly {
  0% { transform: translate(0, 0) rotate(0deg) scale(1); }
  20% { transform: translate(calc(90vw * 0.7), calc(-90vh * 0.4)) rotate(72deg) scale(1.2); }
  40% { transform: translate(calc(-90vw * 0.5), calc(90vh * 0.5)) rotate(144deg) scale(0.8); }
  60% { transform: translate(calc(90vw * 0.3), calc(90vh * 0.6)) rotate(216deg) scale(1.1); }
  80% { transform: translate(calc(-90vw * 0.6), calc(-90vh * 0.3)) rotate(288deg) scale(0.9); }
  100% { transform: translate(0, 0) rotate(360deg) scale(1); }
}

.fly-element {
  animation: fly 5s infinite;
  position: relative;
  z-index: 1;
  will-change: transform;
  transform-origin: center;
  perspective: 1000px;
}

.fly-element:nth-child(2n) {
  animation-delay: 1s;
  animation-duration: 6s;
}

.fly-element:nth-child(3n) {
  animation-delay: 2s;
  animation-duration: 7s;
}

.fly-element:nth-child(4n) {
  animation-delay: 3s;
  animation-duration: 8s;
}

.fly-element:nth-child(5n) {
  animation-delay: 4s;
  animation-duration: 9s;
}

.fly-element:hover {
  animation-play-state: paused;
}

@keyframes rotate-3d {
  0% {
    transform: perspective(1000px) rotateY(0deg) rotateX(0deg);
  }
  25% {
    transform: perspective(1000px) rotateY(90deg) rotateX(45deg);
  }
  50% {
    transform: perspective(1000px) rotateY(180deg) rotateX(0deg);
  }
  75% {
    transform: perspective(1000px) rotateY(270deg) rotateX(-45deg);
  }
  100% {
    transform: perspective(1000px) rotateY(360deg) rotateX(0deg);
  }
}

.rotate-3d {
  animation: rotate-3d 8s infinite linear;
  transform-style: preserve-3d;
  backface-visibility: visible;
}

.rotate-3d:hover {
  animation-play-state: paused;
}
</style>
