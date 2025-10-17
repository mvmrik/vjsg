import { createApp, ref, computed, reactive } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import { createPinia } from 'pinia';
import axios from 'axios';

// Import flag icons CSS
import 'flag-icons/css/flag-icons.min.css';

// Import CoreUI icons CSS
import '@coreui/icons/css/all.min.css';

// Import CoreUI Vue components
import CoreuiVue from '@coreui/vue';
import CIcon from '@coreui/icons-vue';
import * as icons from '@coreui/icons';

// Import components
import GameApp from './components/GameApp.vue';
import HomePage from './components/HomePage.vue';
import ProfilePage from './components/ProfilePage.vue';
import InventoryPage from './components/InventoryPage.vue';
import CityPage from './components/CityPage.vue';
import ParcelEditor from './components/ParcelEditor.vue';
import ObjectEditor from './components/ObjectEditor.vue';
import NotificationsPage from './components/NotificationsPage.vue';

// Setup axios defaults
axios.defaults.baseURL = window.location.origin;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
axios.defaults.withCredentials = true;

// Define routes
const routes = [
  { path: '/', name: 'home', component: HomePage },
  { path: '/city', name: 'city', component: CityPage, meta: { requiresAuth: true } },
  { path: '/city/:parcelId', name: 'parcel-editor', component: ParcelEditor, meta: { requiresAuth: true } },
  { path: '/city/:parcelId/object/:objectId', name: 'object-editor', component: ObjectEditor, meta: { requiresAuth: true } },
  { path: '/notifications', name: 'notifications', component: NotificationsPage, meta: { requiresAuth: true } },
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
  bg: window.translations_bg || {},
});

const translate = computed(() => (key) => {
  const keys = key.split('.');
  const section = keys[0];
  const currentTranslations = translations[currentLocale.value];
  
  if (!currentTranslations || !currentTranslations[section]) {
    return key;
  }
  
  // Navigate through nested keys
  let result = currentTranslations[section];
  for (let i = 1; i < keys.length; i++) {
    if (result && typeof result === 'object' && keys[i] in result) {
      result = result[keys[i]];
    } else {
      return key;
    }
  }
  
  return result || key;
});

// Function to change language
const changeLanguage = (lang) => {
  currentLocale.value = lang;
  localStorage.setItem('app_language', lang);
};

// Function to set language from user profile (for authenticated users)
const setUserLanguage = (userLocale) => {
  if (userLocale && ['en', 'bg'].includes(userLocale)) {
    currentLocale.value = userLocale;
    // Don't save to localStorage for authenticated users - use profile setting
  }
};

app.config.globalProperties.$t = translate.value;
app.config.globalProperties.$changeLanguage = changeLanguage;
app.config.globalProperties.$setUserLanguage = setUserLanguage;
app.provide('$t', translate.value);
app.provide('$changeLanguage', changeLanguage);
app.provide('$setUserLanguage', setUserLanguage);
app.provide('currentLocale', currentLocale);

app.mount('#app');

// Listen for user language change events
window.addEventListener('set-user-language', (event) => {
  const { locale } = event.detail;
  setUserLanguage(locale);
});
