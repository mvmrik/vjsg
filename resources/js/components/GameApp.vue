<template>
  <div class="c-app c-default-layout">
    <!-- Main content wrapper -->
    <div class="c-wrapper">
      <!-- Custom Header with centered icons - only for logged in users -->
      <header v-if="isLoggedIn" class="bg-primary text-white py-3 shadow-sm">
        <div class="container-fluid">
          <div class="d-flex justify-content-center align-items-center">
            <nav class="d-flex gap-4">
              <a @click="$router.push('/map')" :class="['text-decoration-none d-flex flex-column align-items-center p-2 rounded', currentRoute === '/map' ? 'bg-light text-dark' : 'text-white']" style="cursor: pointer;">
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
        <c-footer :fixed="false">
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
    const appVersion = '0.5.0';
    const unreadNotifications = ref(0);

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

    const fetchUnreadNotifications = async () => {
      if (!isLoggedIn.value) return;

      try {
        const response = await fetch('/api/notifications/unread-count');
        const data = await response.json();

        if (data.success) {
          unreadNotifications.value = data.count;
        }
      } catch (error) {
        console.error('Failed to fetch unread notifications:', error);
      }
    };

    onMounted(async () => {
      await gameStore.checkAuthStatus();
      await fetchUnreadNotifications();
      document.addEventListener('click', closeDropdown);
    });

    onUnmounted(() => {
      document.removeEventListener('click', closeDropdown);
    });

    return {
      showLoginModal,
      dropdownVisible,
      isLoggedIn,
      currentUser,
      currentRoute,
      unreadNotifications,
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