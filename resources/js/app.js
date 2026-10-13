import { createApp } from 'vue';
import App from './App.vue';

const el = document.getElementById('app');

if (el) {
    createApp(App).mount(el);
}
