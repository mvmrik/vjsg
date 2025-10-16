import { createApp, ref, computed, reactive } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import { createPinia } from 'pinia';
import axios from 'axios';

// Import flag icons CSS
import 'flag-icons/css/flag-icons.min.css';

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
import ObjectEditor from './components/ObjectEditor.vue';

// Setup axios defaults
axios.defaults.baseURL = window.location.origin;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
axios.defaults.withCredentials = true;

// Define routes
const routes = [
  { path: '/', name: 'home', component: HomePage },
  { path: '/map', name: 'map', component: MapPage, meta: { requiresAuth: true } },
  { path: '/city', name: 'city', component: CityPage, meta: { requiresAuth: true } },
  { path: '/city/:parcelId', name: 'parcel-editor', component: ParcelEditor, meta: { requiresAuth: true } },
  { path: '/city/:parcelId/object/:objectId', name: 'object-editor', component: ObjectEditor, meta: { requiresAuth: true } },
  { path: '/settings', name: 'settings', component: ProfilePage, meta: { requiresAuth: true } },
  { path: '/inventory', name: 'inventory', component: InventoryPage, meta: { requiresAuth: true } }
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

// Add translations
const currentLocale = ref(localStorage.getItem('app_language') || window.locale || 'en');

// Load both languages
const translations = reactive({
  en: window.translations,
  bg: {}
});

// Load Bulgarian translations
fetch('/api/translations/bg')
  .then(res => res.json())
  .then(data => {
    translations.bg = data;
  })
  .catch(err => console.error('Failed to load BG translations:', err));

const translate = computed(() => (key) => {
  const [section, actualKey] = key.split('.');
  const currentTranslations = translations[currentLocale.value];
  return currentTranslations?.[section]?.[actualKey] || key;
});

// Function to change language
const changeLanguage = (lang) => {
  currentLocale.value = lang;
  localStorage.setItem('app_language', lang);
};

app.config.globalProperties.$t = translate.value;
app.config.globalProperties.$changeLanguage = changeLanguage;
app.provide('$t', translate.value);
app.provide('$changeLanguage', changeLanguage);
app.provide('currentLocale', currentLocale);

app.mount('#app');
