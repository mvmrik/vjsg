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
import MarketPage from './components/MarketPage.vue';
import NotificationsPage from './components/NotificationsPage.vue';
import HelpPage from './components/HelpPage.vue';

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
  ,{ path: '/help', name: 'help', component: HelpPage }
  ,{ path: '/market', name: 'market', component: MarketPage, meta: { requiresAuth: true } }
  ,{ path: '/event', name: 'event', component: () => import(/* webpackChunkName: "lottery-page" */ './components/events/LotteryPage.vue'), meta: { requiresAuth: true } }
];

// Create router
const router = createRouter({
  history: createWebHistory(),
  routes
});

// Router guard for authentication
router.beforeEach((to, from, next) => {
  // Consider both session (localStorage) and remember-me cookies when
  // determining if the user is logged in. This prevents the router from
  // redirecting protected routes to '/' before the store has a chance to
  // perform auto-login using the remembered credentials.
  const isLoggedInLocal = localStorage.getItem('game_logged_in') === 'true';

  // Simple cookie parser to check remember-me cookies set by the client
  const cookies = document.cookie.split(';').reduce((acc, cookie) => {
    const [k, v] = cookie.trim().split('=');
    acc[k] = decodeURIComponent(v || '');
    return acc;
  }, {});

  const isLoggedInCookie = cookies.game_logged_in === 'true' && !!cookies.game_private_key;

  const isLoggedIn = isLoggedInLocal || isLoggedInCookie;

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

// Try to fetch server-side translations for the current locale (ensures sections like 'events' are present)
(async () => {
    try {
      const res = await axios.get(`/api/translations/${currentLocale.value}`);
      if (res.data) {
        // merge server-provided translations into existing window-injected translations
        translations[currentLocale.value] = Object.assign({}, translations[currentLocale.value] || {}, res.data);
      }
    } catch (e) {
    // ignore - fallback to server-injected window.translations
    console.warn('Could not fetch translations for', currentLocale.value);
  }
})();

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

app.config.globalProperties.$changeLanguage = changeLanguage;
app.config.globalProperties.$setUserLanguage = setUserLanguage;
// Provide a reactive $t wrapper so consumers always call the latest translate function.
app.config.globalProperties.$t = (key) => translate.value(key);
app.provide('$t', (key) => translate.value(key));
app.provide('$changeLanguage', changeLanguage);
app.provide('$setUserLanguage', setUserLanguage);
app.provide('currentLocale', currentLocale);

app.mount('#app');

// Listen for user language change events
window.addEventListener('set-user-language', (event) => {
  const { locale } = event.detail;
  setUserLanguage(locale);
  // Fetch translations for the newly selected locale so $t() returns proper values
  (async () => {
    try {
      const res = await axios.get(`/api/translations/${locale}`);
      if (res.data) {
        // Merge to avoid overwriting other sections and keep reactivity
        translations[locale] = Object.assign({}, translations[locale] || {}, res.data);
      }
    } catch (e) {
      console.warn('Could not fetch translations for locale', locale);
    }
  })();
});
