import './bootstrap';
import 'primeicons/primeicons.css';
import { createApp } from 'vue';
import OrderPage from './components/OrderPage.vue';
import OrderHistory from './components/OrderHistory.vue';
import axios from 'axios';
import MenuPage from './components/MenuPage.vue'

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.baseURL = '/';
const app = createApp({});
app.component('order-page', OrderPage);
app.component('menu-page', MenuPage);
app.component('order-history', OrderHistory);
app.mount('#app');
