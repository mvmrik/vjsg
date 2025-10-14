import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import { createPinia } from 'pinia';
import axios from 'axios';

// Import CoreUI Vue components
import CoreuiVue from '@coreui/vue';
import CIcon from '@coreui/icons-vue';
import * as icons from '@coreui/icons';

// Import components
import GameApp from './components/GameApp.vue';
import HomePage from './components/HomePage.vue';
import ProfilePage from './components/ProfilePage.vue';
import MapPage from './components/MapPage.vue';
import InventoryPage from './components/InventoryPage.vue';
import CityPage from './components/CityPage.vue';
import ParcelEditor from './components/ParcelEditor.vue';

// Setup axios defaults
axios.defaults.baseURL = window.location.origin;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
axios.defaults.withCredentials = true;

// Define routes
const routes = [
  { path: '/', name: 'home', component: HomePage },
  { path: '/profile', name: 'profile', component: ProfilePage, meta: { requiresAuth: true } },
  { path: '/map', name: 'map', component: MapPage, meta: { requiresAuth: true } },
  { path: '/inventory', name: 'inventory', component: InventoryPage, meta: { requiresAuth: true } },
  { path: '/city', name: 'city', component: CityPage, meta: { requiresAuth: true } },
  { path: '/city/:parcelId', name: 'parcel-editor', component: ParcelEditor, meta: { requiresAuth: true } }
];

// Create router
const router = createRouter({
  history: createWebHistory(),
  routes
});

// Router guard for authentication
router.beforeEach((to, from, next) => {
  const isLoggedIn = localStorage.getItem('game_logged_in') === 'true';
  
  if (to.meta.requiresAuth && !isLoggedIn) {
    next('/');
  } else {
    next();
  }
});

// Create Pinia store
const pinia = createPinia();

// Create and mount app
const app = createApp(GameApp);
app.use(router);
app.use(pinia);
app.use(CoreuiVue);
app.provide('icons', icons);
app.component('CIcon', CIcon);
app.config.globalProperties.$http = axios;
app.mount('#app');
