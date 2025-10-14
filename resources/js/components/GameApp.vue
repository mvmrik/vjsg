<template>
  <div class="c-app c-default-layout">
    <!-- CoreUI Sidebar -->
    <c-sidebar
      :show="sidebarShow"
      @update:show="val => sidebarShow = val"
      :minimize="sidebarMinimize"
      :fixed="true"
    >
      <c-sidebar-brand>
        <div class="h5 mb-0 text-white">Resource Legends</div>
        <template #minimized>
          <strong>RL</strong>
        </template>
      </c-sidebar-brand>

      <c-sidebar-nav
        :nav="navigation"
        navLink="router-link"
      />
    </c-sidebar>

    <!-- Main content wrapper -->
    <div class="c-wrapper">
      <!-- CoreUI Header -->
      <c-header
        :fixed="true"
        class="d-flex"
      >
        <c-sidebar-toggler
          @click="sidebarShow = !sidebarShow"
          class="d-none d-md-flex ml-3"
        />
        
        <div class="breadcrumb ms-2">Resource Legends</div>

        <c-header-nav class="ms-auto mr-4">
          <div v-if="isLoggedIn" class="dropdown position-relative">
            <c-icon
              name="cilMenu"
              size="lg"
              class="cursor-pointer"
              @click="dropdownVisible = !dropdownVisible"
            />
            <div v-show="dropdownVisible" class="dropdown-menu show position-absolute end-0 mt-2" style="z-index: 1000;">
              <a class="dropdown-item" @click="$router.push('/'); dropdownVisible = false;">
                <c-icon name="cilHome" class="me-2" />
                Начало
              </a>
              <a class="dropdown-item" @click="$router.push('/profile'); dropdownVisible = false;">
                <c-icon name="cilUser" class="me-2" />
                Профил
              </a>
              <a class="dropdown-item" @click="$router.push('/map'); dropdownVisible = false;">
                <c-icon name="cilMap" class="me-2" />
                Карта
              </a>
              <a class="dropdown-item" @click="$router.push('/city'); dropdownVisible = false;">
                <c-icon name="cilCity" class="me-2" />
                Град
              </a>
              <a class="dropdown-item" @click="logout(); dropdownVisible = false;">
                <c-icon name="cilAccountLogout" class="me-2" />
                Изход
              </a>
            </div>
          </div>
        </c-header-nav>
      </c-header>

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
              <span>Версия {{ appVersion }}</span>
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
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useGameStore } from '../stores/gameStore';
import LoginForm from './LoginForm.vue';
import appVersion from '../version';

export default {
  name: 'GameApp',
  components: {
    LoginForm
  },
  setup() {
    const gameStore = useGameStore();
    const router = useRouter();
    const sidebarShow = ref(true);
    const sidebarMinimize = ref(false);
    const showLoginModal = ref(false);
    const dropdownVisible = ref(false);

    const currentUser = computed(() => gameStore.user);
    const isLoggedIn = computed(() => localStorage.getItem('game_logged_in') === 'true');

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
        name: 'Профил',
        to: '/profile',
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

    onMounted(() => {
      gameStore.checkAuthStatus();
      document.addEventListener('click', closeDropdown);
    });

    onUnmounted(() => {
      document.removeEventListener('click', closeDropdown);
    });

    return {
      sidebarShow,
      sidebarMinimize,
      showLoginModal,
      dropdownVisible,
      isLoggedIn,
      currentUser,
      navigation,
      logout,
  onLoginSuccess,
  router,
  appVersion
    };
  }
}
</script>

<style>
/* Custom styles */
body { margin: 0; font-family: Inter, ui-sans-serif, system-ui; }
</style>