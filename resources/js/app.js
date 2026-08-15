import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createHead } from '@unhead/vue/client';
import App from './App.vue';
import router from './router';
import { initMetaPixel } from '@/services/metaPixel';
import './styles/main.css';

initMetaPixel();

const app = createApp(App);

app.use(createPinia());
app.use(router);
app.use(createHead());

app.mount('#app');
