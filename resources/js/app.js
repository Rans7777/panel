import './bootstrap';
import 'primeicons/primeicons.css';
import { createApp } from 'vue';
import OrderPage from './components/OrderPage.vue';
import axios from 'axios';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.baseURL = '/';
const app = createApp({});
app.component('order-page', OrderPage);
app.mount('#app');
