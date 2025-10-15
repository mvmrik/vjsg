<template>
  <c-row class="justify-content-center">
    <c-col md="12">
      <!-- Welcome Section -->
      <c-row class="mb-4">
        <c-col md="8">
          <c-card class="mb-4">
            <c-card-body>
              <h1 class="display-5 fw-bold text-primary">Добре дошли в Resource Legends!</h1>
              <p class="lead">
                Влезте в света на събиране на ресурси и стратегическо планиране. 
                Започнете своето приключение днес!
              </p>
              <div class="d-flex gap-2">
                <c-button 
                  color="primary" 
                  size="lg"
                  @click="$router.push('/map')" 
                  v-if="gameStore.isAuthenticated"
                >
                  <c-icon name="cilMap" class="me-2" />
                  Отиди на картата
                </c-button>
                <c-button 
                  color="success" 
                  size="lg"
                  @click="$router.push('/profile')" 
                  v-if="gameStore.isAuthenticated"
                >
                  <c-icon name="cilUser" class="me-2" />
                  Моят профил
                </c-button>
              </div>
            </c-card-body>
          </c-card>
        </c-col>

        <c-col md="4">
          <c-card class="h-100">
            <c-card-body class="d-flex flex-column justify-content-center text-center">
              <c-icon name="cilGamepad" size="3xl" class="text-primary mb-3" />
              <h5>Игрови статус</h5>
              <p v-if="gameStore.isAuthenticated" class="text-success">
                <c-icon name="cilCheckCircle" class="me-1" />
                Влязъл като {{ gameStore.user?.username }}
              </p>
              <p v-else class="text-muted">
                <c-icon name="cilAccountLogout" class="me-1" />
                Не сте влезли в системата
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
                <c-button color="info" @click="$router.push('/profile')">
                  <c-icon name="cilUser" class="me-2" />
                  Редактирай профила
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
              <h4>Влезте в системата</h4>
              <p class="text-muted mb-4">За да започнете играта, моля влезте в системата или се регистрирайте.</p>
              
              <!-- Tab Navigation -->
              <c-nav variant="tabs" role="tablist" class="mb-4 justify-content-center">
                <c-nav-item>
                  <c-nav-link
                    :active="activeAuthTab === 'login'"
                    @click="activeAuthTab = 'login'"
                    class="cursor-pointer"
                  >
                    <c-icon name="cilAccountLogout" class="me-2" />
                    Вход
                  </c-nav-link>
                </c-nav-item>
                <c-nav-item>
                  <c-nav-link
                    :active="activeAuthTab === 'register'"
                    @click="activeAuthTab = 'register'"
                    class="cursor-pointer"
                  >
                    <c-icon name="cilUserPlus" class="me-2" />
                    Регистрация
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
                      placeholder="Частен ключ (64 символа)"
                      type="text"
                      maxlength="64"
                      required
                    />
                  </c-input-group>
                  
                  <c-form-text class="mb-3 text-muted">
                    <c-icon name="cilInfo" class="me-1" />
                    Въведете вашия 64-символен частен ключ за вход в системата
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
                    {{ loading ? 'Влизане...' : 'Влез' }}
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
                      placeholder="Потребителско име"
                      required
                      :minlength="3"
                    />
                  </c-input-group>
                  
                  <c-form-text class="mb-3 text-muted">
                    <c-icon name="cilInfo" class="me-1" />
                    Минимум 3 символа за потребителско име
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
                    {{ loading ? 'Регистрация...' : 'Създай акаунт' }}
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
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useGameStore } from '../stores/gameStore';

export default {
  name: 'HomePage',
  setup() {
    const router = useRouter();
    const gameStore = useGameStore();
    
    const message = ref('');
    const messageType = ref('');
    const loading = ref(false);
    const activeAuthTab = ref('login'); // Default to login tab

    const loginForm = ref({
      privateKey: ''
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
        await gameStore.login(loginForm.value.privateKey);
        showMessage('Успешно влизане!', 'success');
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
        const result = await gameStore.register(registerForm.value.username);
        showMessage(
          `✅ ${result.message}<br><br>
          <strong>📱 Вашите ключове:</strong><br>
          <strong>Публичен:</strong> ${result.user.public_key}<br>
          <strong>Частен:</strong> ${result.user.private_key}<br><br>
          ⚠️ <strong>ВАЖНО:</strong> Запазете частния ключ сигурно - нужен е за влизане!`, 
          'success'
        );
        registerForm.value.username = '';
        
        // Auto switch to login tab after successful registration
        setTimeout(() => {
          activeAuthTab.value = 'login';
        }, 3000);
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
      showMessage,
      collectResources,
      handleLogin,
      handleRegister
    };
  }
}
</script>