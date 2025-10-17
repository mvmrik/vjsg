<template>
  <div class="c-app c-default-layout">
    <!-- Main content wrapper -->
    <div class="c-wrapper">
      <!-- Custom Header with centered icons - only for logged in users -->
      <header v-if="isLoggedIn" class="bg-primary text-white py-3 shadow-sm">
        <div class="container-fluid">
          <div class="d-flex justify-content-center align-items-center">
            <nav class="d-flex gap-4">
              <a @click="$router.push('/')" :class="['text-decoration-none d-flex flex-column align-items-center p-2 rounded', currentRoute === '/' ? 'bg-light text-dark' : 'text-white']" style="cursor: pointer;">
                <c-icon name="cilHome" size="xl" class="mb-1" />
                <small>{{ $t('menu.home') }}</small>
              </a>
              <a @click="$router.push('/city')" :class="['text-decoration-none d-flex flex-column align-items-center p-2 rounded', currentRoute.startsWith('/city') ? 'bg-light text-dark' : 'text-white']" style="cursor: pointer;">
                <c-icon name="cilMap" size="xl" class="mb-1" />
                <small>{{ $t('menu.city') }}</small>
              </a>
              <a @click="$router.push('/notifications')" :class="['text-decoration-none d-flex flex-column align-items-center p-2 rounded position-relative', currentRoute === '/notifications' ? 'bg-light text-dark' : 'text-white']" style="cursor: pointer;">
                <c-icon name="cilBell" size="xl" class="mb-1" />
                <small>{{ $t('menu.notifications') }}</small>
                <c-badge v-if="unreadNotifications > 0" color="danger" class="position-absolute top-0 start-100 translate-middle rounded-pill">
                  {{ unreadNotifications }}
                </c-badge>
              </a>
              <a @click="$router.push('/settings')" :class="['text-decoration-none d-flex flex-column align-items-center p-2 rounded', currentRoute === '/settings' ? 'bg-light text-dark' : 'text-white']" style="cursor: pointer;">
                <c-icon name="cilSettings" size="xl" class="mb-1" />
                <small>{{ $t('menu.settings') }}</small>
              </a>
            </nav>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <div class="c-body">
        <div class="c-main">
          <c-container breakpoint="lg" class="h-auto p-3">
            <router-view />
          </c-container>
        </div>

        <!-- CoreUI Footer -->
        <c-footer class="fixed-bottom">
          <div class="d-flex justify-content-between w-100">
            <div>
              <span>&copy; {{ new Date().getFullYear() }} Resource Legends. Всички права запазени.</span>
            </div>
            <div class="ms-auto">
              <span>{{ $t('global.version') }} {{ appVersion }}</span>
            </div>
          </div>
        </c-footer>
      </div>
    </div>

    <!-- Login Modal -->
    <c-modal
      :visible="showLoginModal"
      @close="showLoginModal = false"
      backdrop="static"
      size="lg"
    >
      <c-modal-header>
        <c-modal-title>Вход в системата</c-modal-title>
      </c-modal-header>
      <c-modal-body>
        <login-form @login-success="onLoginSuccess" />
      </c-modal-body>
    </c-modal>

    <!-- Toast Notifications -->
    <c-toaster placement="bottom-end" class="p-3">
      <c-toast
        v-for="toast in toasts"
        :key="toast.id"
        :visible="true"
        :color="toast.color"
        @close="removeToast(toast.id)"
        autohide
        :delay="5000"
      >
        <c-toast-header>
          <strong class="me-auto">{{ toast.title }}</strong>
        </c-toast-header>
        <c-toast-body>
          {{ toast.message }}
        </c-toast-body>
      </c-toast>
    </c-toaster>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useGameStore } from '../stores/gameStore';
import LoginForm from './LoginForm.vue';

export default {
  name: 'GameApp',
  components: {
    LoginForm
  },
  setup() {
    const gameStore = useGameStore();
    const router = useRouter();
    const route = useRoute();
    const $t = inject('$t');
    const sidebarShow = ref(true);
    const sidebarMinimize = ref(false);
    const showLoginModal = ref(false);
    const dropdownVisible = ref(false);
    const appVersion = '0.6.2';
    const unreadNotifications = computed(() => gameStore.unreadNotificationsCount);
    const toasts = ref([]);

    const currentUser = computed(() => gameStore.user);
    const isLoggedIn = computed(() => gameStore.isAuthenticated);
    const currentRoute = computed(() => route.path);

    const navigation = computed(() => [
      {
        _name: 'CSidebarNavItem',
        name: 'Начало',
        to: '/',
        icon: 'cilHome'
      },
      {
        _name: 'CSidebarNavItem',
        name: 'Карта',
        to: '/map',
        icon: 'cilMap',
        badge: {
          color: 'info',
          text: userParcels.value.length || 0
        }
      },
      {
        _name: 'CSidebarNavItem',
        name: 'Град',
        to: '/city',
        icon: 'cilCity'
      },
      {
        _name: 'CSidebarNavItem',
        name: 'Инвентар',
        to: '/inventory',
        icon: 'cilStorage'
      },
      {
        _name: 'CSidebarNavTitle',
        name: 'Потребител'
      },
      {
        _name: 'CSidebarNavItem',
        name: 'Известия',
        to: '/notifications',
        icon: 'cilBell',
        badge: unreadNotifications.value > 0 ? {
          color: 'danger',
          text: unreadNotifications.value
        } : undefined
      },
      {
        _name: 'CSidebarNavItem',
        name: 'Настройки',
        to: '/settings',
        icon: 'cilUser'
      }
    ]);

    const userParcels = computed(() => {
      return gameStore.parcels?.filter(p => p.user_id === gameStore.user?.id) || [];
    });

    const addToast = (title, message, color = 'info') => {
      const toast = {
        id: Date.now(),
        title,
        message,
        color
      };
      toasts.value.push(toast);
    };

    const removeToast = (id) => {
      toasts.value = toasts.value.filter(toast => toast.id !== id);
    };

    const getNotificationColor = (type) => {
      const colors = {
        info: 'info',
        success: 'success',
        warning: 'warning',
        danger: 'danger',
        functional: 'primary'
      };
      return colors[type] || 'info';
    };

    const logout = () => {
      gameStore.logout();
      router.push('/');
    };

    const onLoginSuccess = () => {
      showLoginModal.value = false;
      // Ensure user stays on home page after login
      if (router.currentRoute.value.path !== '/') {
        router.push('/');
      }
    };

    const closeDropdown = (event) => {
      const dropdown = event.target.closest('.dropdown');
      if (!dropdown) {
        dropdownVisible.value = false;
      }
    };

    onMounted(async () => {
      await gameStore.checkAuthStatus();
      document.addEventListener('click', closeDropdown);
      
      // Listen for notification toast events
      window.addEventListener('show-notification-toast', (event) => {
        const notification = event.detail;
        const color = getNotificationColor(notification.type);
        const translatedTitle = $t(notification.title);
        const translatedMessage = $t(notification.message, notification.data || {});
        addToast(translatedTitle, translatedMessage, color);
      });
    });

    onUnmounted(() => {
      document.removeEventListener('click', closeDropdown);
      window.removeEventListener('show-notification-toast', () => {});
      // Stop polling when component unmounts
      gameStore.stopPolling();
    });

    return {
      showLoginModal,
      dropdownVisible,
      isLoggedIn,
      currentUser,
      currentRoute,
      unreadNotifications,
      toasts,
      addToast,
      removeToast,
      logout,
      onLoginSuccess,
      router,
      appVersion,
      $t
    };
  }
}
</script>

<style>
/* Custom styles */
body { margin: 0; font-family: Inter, ui-sans-serif, system-ui; }
</style>