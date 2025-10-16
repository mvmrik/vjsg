<template>
  <c-row class="justify-content-center">
    <c-col md="12">
      <!-- Language Selector -->
      <div class="d-flex justify-content-end mb-3">
        <div class="btn-group" role="group">
          <button 
            type="button" 
            class="btn btn-outline-primary btn-sm px-2"
            :class="{ active: currentLocale === 'en' }"
            @click="changeLanguage('en')"
          >
            <span class="fi fi-us"></span>
          </button>
          <button 
            type="button" 
            class="btn btn-outline-primary btn-sm px-2"
            :class="{ active: currentLocale === 'bg' }"
            @click="changeLanguage('bg')"
          >
            <span class="fi fi-bg"></span>
          </button>
        </div>
      </div>

      <!-- Welcome Section -->
      <c-row class="mb-4">
        <c-col md="8">
          <c-card class="mb-4">
            <c-card-body>
              <h1 class="display-5 fw-bold text-primary">{{ $t('home.welcome_title') }}</h1>
              <p class="lead">
                {{ $t('home.welcome_description') }}
              </p>
              <div class="d-flex gap-2">
                <c-button 
                  color="primary" 
                  size="lg"
                  @click="$router.push('/map')" 
                  v-if="gameStore.isAuthenticated"
                >
                  <c-icon name="cilMap" class="me-2" />
                  {{ $t('home.go_to_map') }}
                </c-button>
                <c-button 
                  color="success" 
                  size="lg"
                  @click="$router.push('/settings')" 
                  v-if="gameStore.isAuthenticated"
                >
                  <c-icon name="cilUser" class="me-2" />
                  {{ $t('home.my_settings') }}
                </c-button>
              </div>
            </c-card-body>
          </c-card>
        </c-col>

        <c-col md="4">
          <c-card class="h-100">
            <c-card-body class="d-flex flex-column justify-content-center text-center">
              <c-icon name="cilGamepad" size="3xl" class="text-primary mb-3" />
              <h5>{{ $t('home.game_status') }}</h5>
              <p v-if="gameStore.isAuthenticated" class="text-success">
                <c-icon name="cilCheckCircle" class="me-1" />
                {{ $t('home.logged_in_as') }} {{ gameStore.user?.username }}
              </p>
              <p v-else class="text-muted">
                <c-icon name="cilAccountLogout" class="me-1" />
                {{ $t('home.not_logged_in') }}
              </p>
            </c-card-body>
          </c-card>
        </c-col>
      </c-row>

      <!-- Game Stats Dashboard -->
      <c-row class="mb-4" v-if="gameStore.isAuthenticated">
        <c-col sm="6" md="3">
          <c-card class="mb-4 text-white bg-primary">
            <c-card-body class="pb-0 d-flex justify-content-between align-items-start">
              <div>
                <div class="fs-4 fw-semibold">
                  {{ gameStats.resources || 0 }}
                  <span class="fs-6 ms-2 fw-normal">ресурси</span>
                </div>
                <div>Общо ресурси</div>
              </div>
              <c-dropdown>
                <template #toggler="{ on }">
                  <c-button
                    color="transparent"
                    size="sm"
                    v-on="on"
                  >
                    <c-icon name="cilOptions" />
                  </c-button>
                </template>
                <c-dropdown-item>Действие</c-dropdown-item>
                <c-dropdown-item>Още действие</c-dropdown-item>
              </c-dropdown>
            </c-card-body>
          </c-card>
        </c-col>

        <c-col sm="6" md="3">
          <c-card class="mb-4 text-white bg-info">
            <c-card-body class="pb-0 d-flex justify-content-between align-items-start">
              <div>
                <div class="fs-4 fw-semibold">
                  {{ gameStats.level || 1 }}
                  <span class="fs-6 ms-2 fw-normal">ниво</span>
                </div>
                <div>Текущо ниво</div>
              </div>
              <c-dropdown>
                <template #toggler="{ on }">
                  <c-button
                    color="transparent"
                    size="sm"
                    v-on="on"
                  >
                    <c-icon name="cilOptions" />
                  </c-button>
                </template>
                <c-dropdown-item>Действие</c-dropdown-item>
              </c-dropdown>
            </c-card-body>
          </c-card>
        </c-col>

        <c-col sm="6" md="3">
          <c-card class="mb-4 text-white bg-warning">
            <c-card-body class="pb-0 d-flex justify-content-between align-items-start">
              <div>
                <div class="fs-4 fw-semibold">
                  {{ people.total || 0 }}
                  <span class="fs-6 ms-2 fw-normal">популация</span>
                </div>
                <div>Хора (по нива)</div>
                <div class="small text-white-50 mt-1" v-if="people.by_level">
                  <span v-for="(count, lvl) in people.by_level" :key="lvl" class="me-2">LV {{ lvl }}: {{ count }}</span>
                </div>
              </div>
              <c-dropdown>
                <template #toggler="{ on }">
                  <c-button
                    color="transparent"
                    size="sm"
                    v-on="on"
                  >
                    <c-icon name="cilOptions" />
                  </c-button>
                </template>
                <c-dropdown-item>Управление</c-dropdown-item>
              </c-dropdown>
            </c-card-body>
          </c-card>
        </c-col>

        <c-col sm="6" md="3">
          <c-card class="mb-4 text-white bg-danger">
            <c-card-body class="pb-0 d-flex justify-content-between align-items-start">
              <div>
                <div class="fs-4 fw-semibold">
                  {{ gameStats.parcels || 0 }}
                  <span class="fs-6 ms-2 fw-normal">парцели</span>
                </div>
                <div>Мои парцели</div>
              </div>
              <c-dropdown>
                <template #toggler="{ on }">
                  <c-button
                    color="transparent"
                    size="sm"
                    v-on="on"
                  >
                    <c-icon name="cilOptions" />
                  </c-button>
                </template>
                <c-dropdown-item>Управление</c-dropdown-item>
              </c-dropdown>
            </c-card-body>
          </c-card>
        </c-col>
      </c-row>

      <!-- Recent Activity -->
      <c-row v-if="gameStore.isAuthenticated">
        <c-col md="8">
          <c-card class="mb-4">
            <c-card-header>
              <strong>Последна активност</strong>
            </c-card-header>
            <c-card-body>
              <c-list-group flush>
                <c-list-group-item 
                  v-for="activity in recentActivity" 
                  :key="activity.id"
                  class="d-flex justify-content-between align-items-center"
                >
                  <div>
                    <c-icon :name="activity.icon" class="me-2" :class="activity.iconColor" />
                    {{ activity.message }}
                  </div>
                  <c-badge :color="activity.badgeColor">{{ activity.time }}</c-badge>
                </c-list-group-item>
              </c-list-group>
            </c-card-body>
          </c-card>
        </c-col>

        <c-col md="4">
          <c-card class="mb-4">
            <c-card-header>
              <strong>Бързи действия</strong>
            </c-card-header>
            <c-card-body>
              <div class="d-grid gap-2">
                <c-button color="primary" @click="$router.push('/map')">
                  <c-icon name="cilMap" class="me-2" />
                  Отвори картата
                </c-button>
                <c-button color="success" @click="collectResources">
                  <c-icon name="cilStorage" class="me-2" />
                  Събери ресурси
                </c-button>
                <c-button color="info" @click="$router.push('/settings')">
                  <c-icon name="cilUser" class="me-2" />
                  Настройки
                </c-button>
              </div>
            </c-card-body>
          </c-card>
        </c-col>
      </c-row>

      <!-- Login/Register section for non-authenticated users -->
      <c-row v-else>
        <c-col md="8" class="mx-auto">
          <c-card>
            <c-card-body class="text-center py-4">
              <c-icon name="cilUser" size="3xl" class="text-muted mb-3" />
              <h4>{{ $t('home.login_prompt') }}</h4>
              <p class="text-muted mb-4">{{ $t('home.login_prompt') }}</p>
              
              <!-- Tab Navigation -->
              <c-nav variant="tabs" role="tablist" class="mb-4 justify-content-center">
                <c-nav-item>
                  <c-nav-link
                    :active="activeAuthTab === 'login'"
                    @click="activeAuthTab = 'login'"
                    class="cursor-pointer"
                  >
                    <c-icon name="cilAccountLogout" class="me-2" />
                    {{ $t('home.login') }}
                  </c-nav-link>
                </c-nav-item>
                <c-nav-item>
                  <c-nav-link
                    :active="activeAuthTab === 'register'"
                    @click="activeAuthTab = 'register'"
                    class="cursor-pointer"
                  >
                    <c-icon name="cilUserPlus" class="me-2" />
                    {{ $t('home.register') }}
                  </c-nav-link>
                </c-nav-item>
              </c-nav>

              <!-- Login Tab -->
              <div v-show="activeAuthTab === 'login'" class="text-start">
                <c-form @submit.prevent="handleLogin">
                  <c-input-group class="mb-3">
                    <c-input-group-text>
                      <c-icon name="cilLockLocked" />
                    </c-input-group-text>
                    <c-form-input
                      v-model="loginForm.privateKey"
                      :placeholder="$t('home.private_key_placeholder')"
                      type="text"
                      maxlength="64"
                      required
                    />
                  </c-input-group>
                  
                  <div class="form-check mb-3">
                    <input 
                      class="form-check-input" 
                      type="checkbox" 
                      id="rememberMeHome" 
                      v-model="loginForm.rememberMe"
                    >
                    <label class="form-check-label" for="rememberMeHome">
                      {{ $t('global.remember_me') }}
                    </label>
                  </div>
                  
                  <c-form-text class="mb-3 text-muted">
                    <c-icon name="cilInfo" class="me-1" />
                    {{ $t('home.private_key_help') }}
                  </c-form-text>

                  <c-button
                    type="submit"
                    color="primary"
                    class="w-100"
                    :disabled="loading || loginForm.privateKey.length !== 64"
                  >
                    <c-spinner
                      v-if="loading"
                      size="sm"
                      class="me-2"
                    />
                    {{ loading ? $t('home.logging_in') : $t('home.login_button') }}
                  </c-button>
                </c-form>
              </div>

              <!-- Register Tab -->
              <div v-show="activeAuthTab === 'register'" class="text-start">
                <c-form @submit.prevent="handleRegister">
                  <c-input-group class="mb-3">
                    <c-input-group-text>
                      <c-icon name="cilUser" />
                    </c-input-group-text>
                    <c-form-input
                      v-model="registerForm.username"
                      :placeholder="$t('home.username_placeholder')"
                      required
                      :minlength="3"
                    />
                  </c-input-group>
                  
                  <c-form-text class="mb-3 text-muted">
                    <c-icon name="cilInfo" class="me-1" />
                    {{ $t('home.username_help') }}
                  </c-form-text>

                  <c-button
                    type="submit"
                    color="success"
                    class="w-100"
                    :disabled="loading || registerForm.username.length < 3"
                  >
                    <c-spinner
                      v-if="loading"
                      size="sm"
                      class="me-2"
                    />
                    {{ loading ? $t('home.registering') : $t('home.register_button') }}
                  </c-button>
                </c-form>
              </div>
            </c-card-body>
          </c-card>
        </c-col>
      </c-row>

      <!-- Messages -->
      <c-alert
        v-if="message"
        :color="messageType === 'success' ? 'success' : 'danger'"
        :visible="true"
        dismissible
        @close="message = ''"
      >
        <div v-html="message"></div>
      </c-alert>
    </c-col>
  </c-row>
</template>

<script>
import { ref, computed, onMounted, inject } from 'vue';
import { useRouter } from 'vue-router';
import { useGameStore } from '../stores/gameStore';

export default {
  name: 'HomePage',
  setup() {
    const router = useRouter();
    const gameStore = useGameStore();
    const $t = inject('$t');
    const $changeLanguage = inject('$changeLanguage');
    const currentLocale = inject('currentLocale');
    
    const message = ref('');
    const messageType = ref('');
    const loading = ref(false);
    const activeAuthTab = ref('login'); // Default to login tab

    const loginForm = ref({
      privateKey: '',
      rememberMe: false
    });

    const registerForm = ref({
      username: ''
    });

    
    // Mock game stats - replace with real data from store
    const gameStats = ref({
      resources: 1250,
      level: 15,
      experience: 3450,
      parcels: 3
    });

    const people = ref({ total: 0, by_level: null, groups: [] });

    const recentActivity = ref([
      {
        id: 1,
        message: 'Събрахте 50 дърво от парцел #123',
        time: '2 мин',
        icon: 'cilStorage',
        iconColor: 'text-success',
        badgeColor: 'success'
      },
      {
        id: 2,
        message: 'Качихте се на ниво 15',
        time: '1 час',
        icon: 'cilStar',
        iconColor: 'text-warning',
        badgeColor: 'warning'
      },
      {
        id: 3,
        message: 'Закупихте нов парцел',
        time: '3 часа',
        icon: 'cilMap',
        iconColor: 'text-info',
        badgeColor: 'info'
      },
      {
        id: 4,
        message: 'Влязохте в играта',
        time: '5 часа',
        icon: 'cilAccountLogout',
        iconColor: 'text-primary',
        badgeColor: 'primary'
      }
    ]);
    
    const showMessage = (msg, type) => {
      message.value = msg;
      messageType.value = type;
      
      setTimeout(() => {
        message.value = '';
      }, 5000);
    };

    const collectResources = () => {
      showMessage('Ресурсите бяха събрани успешно!', 'success');
      gameStats.value.resources += 25;
      gameStats.value.experience += 10;
    };

    const handleLogin = async () => {
      loading.value = true;
      message.value = '';

      try {
        await gameStore.login(loginForm.value.privateKey, loginForm.value.rememberMe);
        // Login successful, redirect to map
        await router.push('/map');
      } catch (error) {
        showMessage(gameStore.error || 'Грешка при влизане', 'error');
      } finally {
        loading.value = false;
      }
    };

    const handleRegister = async () => {
      loading.value = true;
      message.value = '';

      try {
        await gameStore.register(registerForm.value.username);
        // Registration successful, redirect handled by gameStore
        registerForm.value.username = '';
      } catch (error) {
        showMessage(gameStore.error || 'Грешка при регистрация', 'error');
      } finally {
        loading.value = false;
      }
    };
    
    // Load user stats on mount
    onMounted(() => {
      if (gameStore.isAuthenticated) {
        // Load real game stats here
        gameStore.checkAuthStatus();
        // fetch people info
        fetchPeople();
      }
    });

    const fetchPeople = async () => {
      try {
        const res = await fetch('/api/people');
        const data = await res.json();
        if (data.success) {
          people.value.total = data.total || 0;
          people.value.by_level = data.by_level || {};
          people.value.groups = data.groups || [];
        }
      } catch (e) {
        console.error('Failed to fetch people', e);
      }
    };

    const changeLanguage = (lang) => {
      $changeLanguage(lang);
    };
    
    return {
      message,
      messageType,
      loading,
      activeAuthTab,
      loginForm,
      registerForm,
      gameStats,
      people,
      recentActivity,
      gameStore,
      currentLocale,
      showMessage,
      collectResources,
      handleLogin,
      handleRegister,
      changeLanguage,
      $t
    };
  }
}
</script>